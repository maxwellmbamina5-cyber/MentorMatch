import "server-only";
import { getPool } from "@/lib/db";
import { getSession, type AuthenticatedUser, type UserRole } from "@/lib/session";

export async function getActiveUser(role?: UserRole): Promise<AuthenticatedUser | null> {
  const session = await getSession();
  if (!session.user) {
    return null;
  }

  const result = await getPool().query<AuthenticatedUser>(
    `SELECT id, name, email, role, status
     FROM users
     WHERE id = $1 AND status = 'active'
     LIMIT 1`,
    [session.user.id],
  );
  const user = result.rows[0];
  if (!user || (role && user.role !== role)) {
    return null;
  }

  return user;
}

export async function getRequestUser(request: Request, role?: UserRole): Promise<AuthenticatedUser | null> {
  const authorization = request.headers.get("authorization");
  if (!authorization) {
    return getActiveUser(role);
  }

  const match = authorization.match(/^Bearer\s+(\S+)$/i);
  if (!match) {
    return null;
  }

  const result = await getPool().query<AuthenticatedUser>(
    `SELECT id, name, email, role, status
     FROM users
     WHERE api_token = $1 AND status = 'active'
     LIMIT 1`,
    [match[1]],
  );
  const user = result.rows[0];
  if (!user || (role && user.role !== role)) {
    return null;
  }
  return user;
}

export async function hasActiveSubscription(userId: string): Promise<boolean> {
  const result = await getPool().query<{ active: boolean }>(
    `SELECT EXISTS (
       SELECT 1
       FROM subscriptions
       WHERE user_id = $1
         AND status = 'active'
         AND expires_at > NOW()
     ) AS active`,
    [userId],
  );
  return result.rows[0]?.active === true;
}
