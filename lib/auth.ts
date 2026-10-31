import "server-only";
import { compare, hash } from "bcryptjs";
import { getPool } from "@/lib/db";
import { getSession, type AuthenticatedUser, type UserRole } from "@/lib/session";

interface UserRecord extends AuthenticatedUser {
  password: string;
  phone: string;
}

export class AuthenticationError extends Error {}

export async function authenticateUser(
  email: string,
  password: string,
): Promise<AuthenticatedUser> {
  const result = await getPool().query<UserRecord>(
    `SELECT id, name, email, role, status, password, phone
     FROM users
     WHERE LOWER(email) = LOWER($1)
     LIMIT 1`,
    [email.trim()],
  );
  const user = result.rows[0];

  if (!user || !(await compare(password, user.password))) {
    throw new AuthenticationError("Invalid email or password.");
  }
  if (user.status === "suspended") {
    throw new AuthenticationError("Your account has been suspended. Please contact admin.");
  }
  if (user.status === "rejected") {
    throw new AuthenticationError("Your registration was rejected.");
  }

  const authenticatedUser: AuthenticatedUser = {
    id: user.id,
    name: user.name,
    email: user.email,
    role: user.role,
    status: user.status,
  };
  const session = await getSession();
  session.user = authenticatedUser;
  await session.save();
  return authenticatedUser;
}

const DEFAULT_COURSES = [
  ["CSC101", "Introduction to Computer Science"],
  ["CSC201", "Data Structures and Algorithms"],
  ["CSC301", "Database Systems"],
  ["CSC401", "Software Engineering"],
  ["MAT101", "Calculus I"],
  ["MAT201", "Linear Algebra"],
  ["MAT301", "Statistics"],
  ["PHY101", "Introduction to Physics"],
  ["PHY201", "Physics II"],
  ["ENG101", "English Composition"],
  ["ENG201", "Technical Writing"],
  ["BIO101", "Introduction to Biology"],
  ["CHEM101", "General Chemistry"],
  ["CHEM201", "Organic Chemistry"],
  ["ECON101", "Principles of Economics"],
  ["PSY101", "Introduction to Psychology"],
  ["SOC101", "Introduction to Sociology"],
  ["ACC101", "Principles of Accounting"],
  ["FIN101", "Introduction to Finance"],
  ["MKT101", "Principles of Marketing"],
] as const;

export async function listCourses(): Promise<Array<{ course_code: string; course_name: string }>> {
  const result = await getPool().query<{ course_code: string; course_name: string }>(
    "SELECT course_code, course_name FROM courses ORDER BY course_code ASC",
  );
  return result.rows.length > 0
    ? result.rows
    : DEFAULT_COURSES.map(([course_code, course_name]) => ({ course_code, course_name }));
}

interface RegistrationInput {
  name: string;
  email: string;
  phone: string;
  password: string;
  role: Exclude<UserRole, "admin">;
  programme: string;
  yearOfStudy: string;
  courseCode: string;
  courseName: string;
  grade: string;
}

export async function registerUser(input: RegistrationInput): Promise<void> {
  const pool = getPool();
  const client = await pool.connect();
  const passwordHash = await hash(input.password, 10);

  try {
    await client.query("BEGIN");

    let semesterResult = await client.query<{ id: string }>(
      `SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1`,
    );
    if (!semesterResult.rows[0]) {
      const currentYear = new Date().getFullYear();
      semesterResult = await client.query<{ id: string }>(
        `INSERT INTO semesters
           (semester_name, start_date, end_date, registration_open, registration_close, status)
         VALUES ($1, CURRENT_DATE, CURRENT_DATE + INTERVAL '6 months',
                 CURRENT_DATE, CURRENT_DATE + INTERVAL '1 month', 'active')
         RETURNING id`,
        [`${currentYear}-1`],
      );
    }
    const semesterId = semesterResult.rows[0]?.id;
    if (!semesterId) {
      throw new Error("Could not find or create an active semester.");
    }

    let courseName = input.courseName.trim();
    if (input.courseCode) {
      const courseResult = await client.query<{ course_name: string }>(
        "SELECT course_name FROM courses WHERE course_code = $1 LIMIT 1",
        [input.courseCode],
      );
      if (courseResult.rows[0]) {
        courseName = courseResult.rows[0].course_name;
      } else {
        courseName ||= `${input.courseCode} (Custom)`;
        await client.query(
          "INSERT INTO courses (course_code, course_name) VALUES ($1, $2) ON CONFLICT (course_code) DO NOTHING",
          [input.courseCode, courseName],
        );
      }
    }

    const userResult = await client.query<{ id: string }>(
      `INSERT INTO users
         (name, email, phone, password, role, programme, year_of_study, status)
       VALUES ($1, $2, $3, $4, $5, $6, $7, 'pending')
       RETURNING id`,
      [
        input.name,
        input.email,
        input.phone,
        passwordHash,
        input.role,
        input.programme || null,
        input.yearOfStudy || null,
      ],
    );
    const userId = userResult.rows[0]?.id;
    if (!userId) {
      throw new Error("Account creation did not return a user ID.");
    }

    if (input.role === "mentor") {
      await client.query(
        `INSERT INTO mentor_applications
           (mentor_id, semester_id, course_code, course_name, grade, status)
         VALUES ($1, $2, $3, $4, $5, 'pending')`,
        [userId, semesterId, input.courseCode, courseName, input.grade],
      );
    } else {
      await client.query(
        `INSERT INTO mentee_enrollments
           (mentee_id, semester_id, course_code, course_name, status)
         VALUES ($1, $2, $3, $4, 'pending')`,
        [
          userId,
          semesterId,
          input.courseCode || "PENDING",
          input.courseCode ? courseName : "No course selected yet",
        ],
      );
    }

    await client.query(
      "INSERT INTO system_logs (user_id, action) VALUES ($1, $2)",
      [userId, `User registered as ${input.role}`],
    );
    await client.query("COMMIT");
  } catch (error) {
    await client.query("ROLLBACK");
    if (
      typeof error === "object"
      && error !== null
      && "code" in error
      && error.code === "23505"
    ) {
      throw new AuthenticationError("Email or phone number is already registered.");
    }
    throw error;
  } finally {
    client.release();
  }
}

export async function signOut(): Promise<void> {
  const session = await getSession();
  session.destroy();
}
