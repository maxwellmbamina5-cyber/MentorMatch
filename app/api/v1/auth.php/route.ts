import { randomBytes } from "node:crypto";
import { compare } from "bcryptjs";
import { apiOptions, withApiCors } from "@/lib/api-cors";
import { getPool } from "@/lib/db";

interface LoginUser {
  id: string;
  name: string;
  email: string;
  role: string;
  status: string;
  password: string;
}

export async function POST(request: Request) {
  const parameters = new URL(request.url).searchParams;
  if (parameters.get("action") !== "login") {
    return withApiCors(
      Response.json({ success: false, message: "Unsupported authentication action." }, { status: 400 }),
      "POST, GET, OPTIONS",
    );
  }

  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return withApiCors(
      Response.json({ success: false, message: "Request body must be valid JSON." }, { status: 400 }),
      "POST, GET, OPTIONS",
    );
  }
  if (!body || typeof body !== "object") {
    return withApiCors(
      Response.json({ success: false, message: "Email and password required." }, { status: 400 }),
      "POST, GET, OPTIONS",
    );
  }

  const input = body as Record<string, unknown>;
  const email = typeof input.email === "string" ? input.email.trim() : "";
  const password = typeof input.password === "string" ? input.password : "";
  if (!email || !password) {
    return withApiCors(
      Response.json({ success: false, message: "Email and password required." }, { status: 400 }),
      "POST, GET, OPTIONS",
    );
  }

  const result = await getPool().query<LoginUser>(
    `SELECT id, name, email, role, status, password
     FROM users
     WHERE LOWER(email) = LOWER($1)
     LIMIT 1`,
    [email],
  );
  const user = result.rows[0];
  if (!user || !(await compare(password, user.password))) {
    return withApiCors(
      Response.json({ success: false, message: "Invalid credentials." }, { status: 401 }),
      "POST, GET, OPTIONS",
    );
  }
  if (user.status !== "active") {
    return withApiCors(
      Response.json({ success: false, message: "Account not active." }, { status: 403 }),
      "POST, GET, OPTIONS",
    );
  }

  const token = randomBytes(32).toString("hex");
  await getPool().query("UPDATE users SET api_token = $1 WHERE id = $2", [token, user.id]);
  return withApiCors(Response.json({
      success: true,
      data: {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role,
        token,
      },
    }), "POST, GET, OPTIONS");
}

export function OPTIONS() {
  return apiOptions("POST, GET, OPTIONS");
}
