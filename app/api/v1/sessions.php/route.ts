import {
  GET as getSessions,
  POST as createSession,
} from "../../sessions/route";
import { apiOptions, withApiCors } from "@/lib/api-cors";

export async function GET(request: Request) {
  return withApiCors(await getSessions(request), "GET, POST, PUT, DELETE, OPTIONS");
}

export async function POST(request: Request) {
  return withApiCors(await createSession(request), "GET, POST, PUT, DELETE, OPTIONS");
}

export function OPTIONS() {
  return apiOptions("GET, POST, PUT, DELETE, OPTIONS");
}
