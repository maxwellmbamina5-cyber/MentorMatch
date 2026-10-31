import "server-only";
import { getPool } from "@/lib/db";

export type MentorSearchType = "all" | "course" | "mentor" | "programme" | "topic";
export type MentorSort = "relevance" | "rating" | "price" | "name";

export interface MentorSearchOptions {
  query: string;
  type: MentorSearchType;
  minRating: number;
  maxRate: number;
  sort: MentorSort;
}

export interface MentorSearchResult {
  id: string;
  name: string;
  programme: string | null;
  year_of_study: string | null;
  course_code: string;
  course_name: string;
  rate_per_hour: string;
  avg_rating: string;
  total_reviews: string;
  completed_sessions: string;
}

const topicAliases: Record<string, string[]> = {
  math: ["mathematics", "calculus", "algebra", "statistics"],
  programming: ["coding", "computer science", "software", "javascript", "python"],
  science: ["biology", "chemistry", "physics", "laboratory"],
  business: ["accounting", "finance", "marketing", "economics"],
  english: ["writing", "literature", "composition", "grammar"],
  engineering: ["mechanical", "electrical", "civil", "chemical"],
};

function getSearchTerms(query: string, type: MentorSearchType): string[] {
  const normalized = query.trim().slice(0, 120);
  if (!normalized) {
    return [];
  }

  const terms = [normalized];
  if (type === "topic") {
    for (const [topic, aliases] of Object.entries(topicAliases)) {
      if (topic.includes(normalized.toLowerCase()) || aliases.some((alias) => alias.includes(normalized.toLowerCase()))) {
        terms.push(topic, ...aliases);
      }
    }
  }

  return [...new Set(terms)].map((term) => `%${term.replace(/[\\%_]/g, "\\$&")}%`);
}

export async function searchMentors(
  semesterId: string,
  options: MentorSearchOptions,
): Promise<MentorSearchResult[]> {
  const values: unknown[] = [semesterId];
  const filters = [
    "u.role = 'mentor'",
    "u.status = 'active'",
    "ma.status = 'approved'",
    "ma.semester_id = $1",
    `EXISTS (
      SELECT 1 FROM subscriptions sub
      WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
    )`,
  ];

  const terms = getSearchTerms(options.query, options.type);
  if (terms.length > 0) {
    values.push(terms);
    const termParameter = `$${values.length}::text[]`;
    const searchColumns = options.type === "course"
      ? ["r.course_code", "c.course_name", "ma.course_name"]
      : options.type === "mentor"
        ? ["u.name"]
        : options.type === "programme"
          ? ["u.programme"]
          : ["u.name", "u.programme", "r.course_code", "c.course_name", "ma.course_name"];
    filters.push(`(${searchColumns.map((column) => `${column} ILIKE ANY(${termParameter})`).join(" OR ")})`);
  }

  values.push(options.minRating);
  filters.push(`COALESCE((SELECT AVG(f.rating) FROM feedback f WHERE f.mentor_id = u.id), 0) >= $${values.length}`);
  values.push(options.maxRate);
  filters.push(`r.rate_per_hour <= $${values.length}`);

  const ordering: Record<MentorSort, string> = {
    relevance: "avg_rating DESC, total_reviews DESC, u.name ASC",
    rating: "avg_rating DESC, total_reviews DESC, u.name ASC",
    price: "r.rate_per_hour ASC, avg_rating DESC, u.name ASC",
    name: "u.name ASC",
  };
  const result = await getPool().query<MentorSearchResult>(
    `SELECT DISTINCT
       u.id, u.name, u.programme, u.year_of_study, r.course_code,
       COALESCE(NULLIF(c.course_name, ''), NULLIF(ma.course_name, ''), r.course_code) AS course_name,
       r.rate_per_hour,
       COALESCE((SELECT AVG(f.rating) FROM feedback f WHERE f.mentor_id = u.id), 0) AS avg_rating,
       (SELECT COUNT(*) FROM feedback f WHERE f.mentor_id = u.id) AS total_reviews,
       (SELECT COUNT(*) FROM sessions s WHERE s.mentor_id = u.id AND s.status = 'completed') AS completed_sessions
     FROM users u
     JOIN mentor_rates r ON r.mentor_id = u.id
     JOIN mentor_applications ma ON ma.mentor_id = u.id AND ma.course_code = r.course_code
     LEFT JOIN courses c ON c.course_code = r.course_code
     WHERE ${filters.join(" AND ")}
     ORDER BY ${ordering[options.sort]}
     LIMIT 100`,
    values,
  );

  return result.rows;
}

export async function getActiveSemesterId(): Promise<string | null> {
  const result = await getPool().query<{ id: string }>(
    "SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1",
  );
  return result.rows[0]?.id ?? null;
}
