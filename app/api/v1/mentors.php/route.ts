import { GET as getMentors } from "../../mentors/route";
import { apiOptions, withApiCors } from "@/lib/api-cors";

export async function GET(request: Request) {
  return withApiCors(await getMentors(request), "GET, POST, OPTIONS");
}

export function OPTIONS() {
  return apiOptions("GET, POST, OPTIONS");
}
