import "server-only";
import { getPool } from "@/lib/db";

interface BookingInput {
  mentorId: unknown;
  courseCode: unknown;
  sessionDate: unknown;
  sessionTime: unknown;
  durationMinutes: unknown;
}

interface BookingResult {
  session_id: string;
  payment: {
    total: number;
    admin_fee: number;
    mentor_payout: number;
  };
}

export class BookingError extends Error {
  constructor(message: string, readonly status: number = 400) {
    super(message);
  }
}

function validateBookingInput(input: BookingInput) {
  const mentorId = typeof input.mentorId === "number" || typeof input.mentorId === "string"
    ? String(input.mentorId)
    : "";
  const courseCode = typeof input.courseCode === "string" ? input.courseCode.trim().toUpperCase() : "";
  const sessionDate = typeof input.sessionDate === "string" ? input.sessionDate : "";
  const sessionTime = typeof input.sessionTime === "string" ? input.sessionTime : "";
  const durationMinutes = Number(input.durationMinutes);

  if (
    !/^\d{1,19}$/.test(mentorId)
    || BigInt(mentorId) > BigInt("9223372036854775807")
    || !courseCode
    || courseCode.length > 40
  ) {
    throw new BookingError("Choose a valid mentor and course.");
  }
  if (!/^\d{4}-\d{2}-\d{2}$/.test(sessionDate) || !/^\d{2}:\d{2}$/.test(sessionTime)) {
    throw new BookingError("Choose a valid session date and start time.");
  }
  if (![30, 60, 90, 120].includes(durationMinutes)) {
    throw new BookingError("Session duration must be 30, 60, 90, or 120 minutes.");
  }

  const [year, month, day] = sessionDate.split("-").map(Number);
  const [hour, minute] = sessionTime.split(":").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  if (
    date.getUTCFullYear() !== year
    || date.getUTCMonth() !== month - 1
    || date.getUTCDate() !== day
    || hour > 23
    || minute > 59
    || (hour * 60 + minute + durationMinutes) > 24 * 60
  ) {
    throw new BookingError("Choose a valid session date, start time, and duration.");
  }

  const lusakaNow = new Date(Date.now() + 2 * 60 * 60 * 1000);
  const today = lusakaNow.toISOString().slice(0, 10);
  const todayDate = new Date(`${today}T00:00:00.000Z`);
  const latestAllowed = new Date(todayDate.getTime() + 14 * 24 * 60 * 60 * 1000);
  if (sessionDate < today || date > latestAllowed) {
    throw new BookingError("Choose a date within the next 14 days.");
  }

  const sessionStart = Date.parse(`${sessionDate}T${sessionTime}:00+02:00`);
  if (!Number.isFinite(sessionStart) || sessionStart <= Date.now()) {
    throw new BookingError("Session date and time cannot be in the past.");
  }

  return {
    mentorId,
    courseCode,
    sessionDate,
    sessionTime,
    durationMinutes,
    dayOfWeek: date.getUTCDay(),
  };
}

export async function createSessionBooking(menteeId: string, input: BookingInput): Promise<BookingResult> {
  const booking = validateBookingInput(input);
  const client = await getPool().connect();

  try {
    await client.query("BEGIN");
    await client.query(
      "SELECT pg_advisory_xact_lock(hashtext($1), hashtext($2))",
      [booking.mentorId, booking.sessionDate],
    );

    const semester = await client.query<{ id: string }>(
      "SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1",
    );
    const semesterId = semester.rows[0]?.id;
    if (!semesterId) {
      throw new BookingError("No active semester is available.", 409);
    }

    const eligibleMentor = await client.query<{ rate_per_hour: string }>(
      `SELECT r.rate_per_hour
       FROM users u
       JOIN mentor_rates r ON r.mentor_id = u.id AND r.course_code = $2
       JOIN mentor_applications ma ON ma.mentor_id = u.id AND ma.course_code = r.course_code
       WHERE u.id = $1 AND u.role = 'mentor' AND u.status = 'active'
         AND ma.status = 'approved' AND ma.semester_id = $3
         AND EXISTS (
           SELECT 1 FROM subscriptions sub
           WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
         )
       LIMIT 1`,
      [booking.mentorId, booking.courseCode, semesterId],
    );
    const rate = Number(eligibleMentor.rows[0]?.rate_per_hour);
    if (!Number.isFinite(rate) || rate <= 0) {
      throw new BookingError("This mentor is not available for the selected course.", 404);
    }

    const menteeSubscription = await client.query<{ active: boolean }>(
      `SELECT EXISTS (
         SELECT 1 FROM subscriptions
         WHERE user_id = $1 AND status = 'active' AND expires_at > NOW()
       ) AS active`,
      [menteeId],
    );
    if (menteeSubscription.rows[0]?.active !== true) {
      throw new BookingError("An active subscription is required to book a session.", 403);
    }

    const startMinutes = Number(booking.sessionTime.slice(0, 2)) * 60
      + Number(booking.sessionTime.slice(3, 5));
    const endMinutes = startMinutes + booking.durationMinutes;
    const startTime = `${booking.sessionTime}:00`;
    const endTime = `${String(Math.floor(endMinutes / 60)).padStart(2, "0")}:${String(endMinutes % 60).padStart(2, "0")}:00`;

    const availability = await client.query(
      `SELECT 1 FROM mentor_availability
       WHERE mentor_id = $1 AND day_of_week = $2 AND is_recurring = 1
         AND start_time <= $3::time AND end_time >= $4::time
       LIMIT 1`,
      [booking.mentorId, booking.dayOfWeek, startTime, endTime],
    );
    if (availability.rowCount === 0) {
      throw new BookingError("Mentor is not available at that time.");
    }

    const sessionStart = `${booking.sessionDate} ${startTime}`;
    const sessionEnd = `${booking.sessionDate} ${endTime}`;
    const conflicts = await client.query(
      `SELECT 1 FROM sessions
       WHERE mentor_id = $1 AND status IN ('pending', 'confirmed')
         AND session_date < $2::timestamp
         AND session_date + duration_minutes * INTERVAL '1 minute' > $3::timestamp
       LIMIT 1`,
      [booking.mentorId, sessionEnd, sessionStart],
    );
    if (conflicts.rowCount !== 0) {
      throw new BookingError("Mentor is not available at that time. Please choose another time.");
    }

    const menteeConflicts = await client.query(
      `SELECT 1 FROM sessions
       WHERE mentee_id = $1 AND status IN ('pending', 'confirmed')
         AND session_date < $2::timestamp
         AND session_date + duration_minutes * INTERVAL '1 minute' > $3::timestamp
       LIMIT 1`,
      [menteeId, sessionEnd, sessionStart],
    );
    if (menteeConflicts.rowCount !== 0) {
      throw new BookingError("You already have a session scheduled at that time.");
    }

    const total = Math.round(rate * (booking.durationMinutes / 60) * 100) / 100;
    const adminFee = Math.round(total * 0.05 * 100) / 100;
    const mentorPayout = Math.round((total - adminFee) * 100) / 100;
    const created = await client.query<{ id: string }>(
      `INSERT INTO sessions
         (mentor_id, mentee_id, semester_id, course_code, session_date,
          duration_minutes, mentor_rate, total_amount, admin_fee, mentor_payout,
          status, payment_status)
       VALUES ($1, $2, $3, $4, $5::timestamp, $6, $7, $8, $9, $10, 'pending', 'pending')
       RETURNING id`,
      [
        booking.mentorId,
        menteeId,
        semesterId,
        booking.courseCode,
        sessionStart,
        booking.durationMinutes,
        rate,
        total,
        adminFee,
        mentorPayout,
      ],
    );
    const sessionId = created.rows[0]?.id;
    if (!sessionId) {
      throw new Error("Session booking did not return an ID.");
    }

    await client.query(
      "INSERT INTO system_logs (user_id, action) VALUES ($1, $2)",
      [menteeId, `Booked session #${sessionId} with mentor #${booking.mentorId}`],
    );
    await client.query("COMMIT");

    return {
      session_id: sessionId,
      payment: {
        total,
        admin_fee: adminFee,
        mentor_payout: mentorPayout,
      },
    };
  } catch (error) {
    await client.query("ROLLBACK");
    throw error;
  } finally {
    client.release();
  }
}
