import { Pool } from "pg";

const globalForPostgres = globalThis as typeof globalThis & {
  postgresPool?: Pool;
};

export function getPool(): Pool {
  if (globalForPostgres.postgresPool) {
    return globalForPostgres.postgresPool;
  }

  const connectionString = process.env.DATABASE_URL;
  const pool = connectionString
    ? new Pool({ connectionString })
    : new Pool({
        host: process.env.DB_HOST ?? "localhost",
        port: Number(process.env.DB_PORT ?? "5432"),
        database: process.env.DB_NAME ?? "mentormatch",
        user: process.env.DB_USER ?? "postgres",
        password: process.env.DB_PASSWORD ?? "",
      });
  globalForPostgres.postgresPool = pool;
  return pool;
}
