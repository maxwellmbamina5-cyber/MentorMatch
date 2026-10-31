import { getRequestUser, hasActiveSubscription } from "@/lib/access";
import { getPool } from "@/lib/db";
import { createSessionBooking, BookingError } from "@/lib/session-booking";

interface SessionQueryResult {
  id: string;
  mentor_id: string;
  mentee_id: string;
  course_code: string;
  session_date: string;
  duration_minutes: number;
  status: string;
  payment_status: string;
  mentor_name: string;
  mentee_name: string;
}

export async function GET(request: Request) {
  const user = await getRequestUser(request);
  if (!user || (user.role !== "mentor" && user.role !== "mentee")) {
    return Response.json({ success: false, message: "Sign in as a mentor or mentee." }, { status: 401 });
  }

  const parameters = new URL(request.url).searchParams;
  const requestedRole = parameters.get("role");
  if (requestedRole && requestedRole !== user.role) {
    return Response.json({ success: false, message: "You cannot view another user's sessions." }, { status: 403 });
  }
  const status = parameters.get("status");
  const values: unknown[] = [user.id];
  const filters = [user.role === "mentor" ? "s.mentor_id = $1" : "s.mentee_id = $1"];
  if (status) {
    if (!["pending", "confirmed", "completed", "cancelled", "no_show"].includes(status)) {
      return Response.json({ success: false, message: "Invalid session status." }, { status: 400 });
    }
    values.push(status);
    filters.push(`s.status = $${values.length}`);
  }

  const result = await getPool().query<SessionQueryResult>(
    `SELECT s.id, s.mentor_id, s.mentee_id, s.course_code, s.session_date,
            s.duration_minutes, s.status, s.payment_status,
            m.name AS mentor_name, n.name AS mentee_name
     FROM sessions s
     JOIN users m ON m.id = s.mentor_id
     JOIN users n ON n.id = s.mentee_id
     WHERE ${filters.join(" AND ")}
     ORDER BY s.session_date ASC
     LIMIT 200`,
    values,
  );
  return Response.json({ success: true, data: result.rows });
}

export async function POST(request: Request) {
  const user = await getRequestUser(request, "mentee");
  if (!user) {
    return Response.json({ success: false, message: "Sign in with an active mentee account." }, { status: 401 });
  }
  if (!(await hasActiveSubscription(user.id))) {
    return Response.json(
      { success: false, message: "An active subscription is required to book a session." },
      { status: 403 },
    );
  }

  let input: unknown;
  try {
    input = await request.json();
  } catch {
    return Response.json({ success: false, message: "Request body must be valid JSON." }, { status: 400 });
  }
  if (!input || typeof input !== "object") {
    return Response.json({ success: false, message: "Request body must be a JSON object." }, { status: 400 });
  }

  const body = input as Record<string, unknown>;
  let sessionDate = body.session_date;
  let sessionTime = body.session_time;
  if (typeof sessionDate === "string" && typeof sessionTime !== "string") {
    const match = sessionDate.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::\d{2})?$/);
    if (match) {
      sessionDate = match[1];
      sessionTime = match[2];
    }
  }
  try {
    const booking = await createSessionBooking(user.id, {
      mentorId: body.mentor_id,
      courseCode: body.course_code,
      sessionDate,
      sessionTime,
      durationMinutes: body.duration_minutes,
    });
    return Response.json({ success: true, data: booking }, { status: 201 });
  } catch (error) {
    if (error instanceof BookingError) {
      return Response.json({ success: false, message: error.message }, { status: error.status });
    }
    throw error;
  }
}
