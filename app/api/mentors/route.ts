import { getRequestUser, hasActiveSubscription } from "@/lib/access";
import {
  getActiveSemesterId,
  searchMentors,
  type MentorSearchType,
  type MentorSort,
} from "@/lib/mentors";

const searchTypes = new Set<MentorSearchType>(["all", "course", "mentor", "programme", "topic"]);
const sortOptions = new Set<MentorSort>(["relevance", "rating", "price", "name"]);

export async function GET(request: Request) {
  const user = await getRequestUser(request, "mentee");
  if (!user) {
    return Response.json(
      { success: false, message: "Sign in with an active mentee account to search for mentors." },
      { status: 401 },
    );
  }
  if (!(await hasActiveSubscription(user.id))) {
    return Response.json(
      { success: false, message: "An active subscription is required to search for mentors." },
      { status: 403 },
    );
  }

  const parameters = new URL(request.url).searchParams;
  const query = parameters.get("search") ?? parameters.get("course") ?? "";
  const requestedType = parameters.get("search_type") ?? (parameters.has("course") ? "course" : "all");
  const requestedSort = parameters.get("sort") ?? "relevance";
  const ratingParameter = Number(parameters.get("min_rating") ?? "0");
  const rateParameter = Number(parameters.get("max_rate") ?? "500");

  if (
    !searchTypes.has(requestedType as MentorSearchType)
    || !sortOptions.has(requestedSort as MentorSort)
    || !Number.isFinite(ratingParameter)
    || ratingParameter < 0
    || ratingParameter > 5
    || !Number.isFinite(rateParameter)
    || rateParameter < 0
  ) {
    return Response.json(
      { success: false, message: "One or more search filters are invalid." },
      { status: 400 },
    );
  }

  const semesterId = await getActiveSemesterId();
  const mentors = semesterId
    ? await searchMentors(semesterId, {
        query,
        type: requestedType as MentorSearchType,
        minRating: ratingParameter,
        maxRate: rateParameter,
        sort: requestedSort as MentorSort,
      })
    : [];

  return Response.json({ success: true, data: mentors });
}
