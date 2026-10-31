import Link from "next/link";
import { registerAction } from "@/app/register/actions";
import { listCourses } from "@/lib/auth";

interface RegisterPageProps {
  searchParams: Promise<{ error?: string }>;
}

const programmes = [
  "BSc Computer Science",
  "BSc Information Technology",
  "BSc Software Engineering",
  "BSc Business Administration",
  "BSc Economics",
  "BSc Mathematics",
  "BSc Physics",
  "BSc Chemistry",
  "BSc Biology",
  "BSc Psychology",
  "BA English",
  "BA Sociology",
  "BSc Engineering",
  "BSc Accounting",
  "BSc Finance",
  "BSc Marketing",
  "BSc Nursing",
  "BSc Public Health",
  "LLB Law",
  "BSc Architecture",
  "BSc Environmental Science",
  "BA Political Science",
  "BA History",
  "BSc Agriculture",
  "BSc Food Science",
];

export default async function RegisterPage({ searchParams }: RegisterPageProps) {
  const [{ error }, courses] = await Promise.all([searchParams, listCourses()]);

  return (
    <main className="container py-4">
      <section className="card registration-card mx-auto" style={{ maxWidth: 760 }}>
        <div className="registration-header">
          <h1 className="h2">Join MentorMatch</h1>
          <p>Create an account to learn or mentor with your campus community.</p>
        </div>
        <div className="card-body p-4 p-md-5">
          {error && <div className="alert alert-danger" role="alert">{error}</div>}
          <form action={registerAction}>
            <div className="row g-3">
              <div className="col-md-6">
                <label htmlFor="name" className="form-label">Full name</label>
                <input id="name" name="name" className="form-control" maxLength={150} required />
              </div>
              <div className="col-md-6">
                <label htmlFor="email" className="form-label">Email address</label>
                <input id="email" name="email" type="email" className="form-control" maxLength={255} required />
              </div>
              <div className="col-md-6">
                <label htmlFor="phone" className="form-label">Phone number</label>
                <input id="phone" name="phone" type="tel" className="form-control" maxLength={40} required />
              </div>
              <div className="col-md-6">
                <label htmlFor="role" className="form-label">I want to</label>
                <select id="role" name="role" className="form-select" required defaultValue="mentee">
                  <option value="mentee">Find a mentor</option>
                  <option value="mentor">Become a mentor</option>
                </select>
              </div>
              <div className="col-md-6">
                <label htmlFor="password" className="form-label">Password</label>
                <input id="password" name="password" type="password" className="form-control" minLength={6} required />
              </div>
              <div className="col-md-6">
                <label htmlFor="confirm_password" className="form-label">Confirm password</label>
                <input id="confirm_password" name="confirm_password" type="password" className="form-control" minLength={6} required />
              </div>
              <div className="col-md-6">
                <label htmlFor="programme" className="form-label">Programme of study</label>
                <select id="programme" name="programme" className="form-select" defaultValue="">
                  <option value="">Select a programme</option>
                  {programmes.map((programme) => <option key={programme}>{programme}</option>)}
                </select>
              </div>
              <div className="col-md-6">
                <label htmlFor="year_of_study" className="form-label">Year of study</label>
                <select id="year_of_study" name="year_of_study" className="form-select" defaultValue="">
                  <option value="">Select a year</option>
                  {[1, 2, 3, 4, 5, 6].map((year) => (
                    <option key={year} value={String(year)}>Year {year}</option>
                  ))}
                </select>
              </div>
              <div className="col-md-8">
                <label htmlFor="course_code" className="form-label">Course</label>
                <select id="course_code" name="course_code" className="form-select" defaultValue="">
                  <option value="">No course selected yet</option>
                  {courses.map((course) => (
                    <option key={course.course_code} value={course.course_code}>
                      {course.course_code} — {course.course_name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="col-md-4">
                <label htmlFor="grade" className="form-label">Grade (mentors)</label>
                <select id="grade" name="grade" className="form-select" defaultValue="">
                  <option value="">Select grade</option>
                  {["A", "B+", "B", "C+", "C"].map((grade) => (
                    <option key={grade} value={grade}>{grade}</option>
                  ))}
                </select>
              </div>
              <div className="col-12">
                <div className="form-check">
                  <input id="agree_terms" name="agree_terms" type="checkbox" className="form-check-input" required />
                  <label htmlFor="agree_terms" className="form-check-label">
                    I agree to the Terms and Conditions.
                  </label>
                </div>
              </div>
              <div className="col-12">
                <button type="submit" className="btn btn-primary btn-lg w-100">Create account</button>
              </div>
            </div>
          </form>
          <p className="text-center mt-4 mb-0">
            Already registered? <Link href="/login">Sign in</Link>
          </p>
        </div>
      </section>
    </main>
  );
}
