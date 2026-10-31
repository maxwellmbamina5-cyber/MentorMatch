"use server";

import { redirect } from "next/navigation";
import { AuthenticationError, registerUser } from "@/lib/auth";

const VALID_ROLES = new Set(["mentor", "mentee"]);

export async function registerAction(formData: FormData): Promise<void> {
  const name = String(formData.get("name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const phone = String(formData.get("phone") ?? "").trim();
  const password = String(formData.get("password") ?? "");
  const confirmPassword = String(formData.get("confirm_password") ?? "");
  const role = String(formData.get("role") ?? "");
  const programme = String(formData.get("programme") ?? "").trim();
  const yearOfStudy = String(formData.get("year_of_study") ?? "").trim();
  const courseCode = String(formData.get("course_code") ?? "").trim().toUpperCase();
  const courseName = String(formData.get("course_name") ?? "").trim();
  const grade = String(formData.get("grade") ?? "").trim();
  const agreeTerms = formData.get("agree_terms") === "on";

  let error: string | null = null;
  if (!name || !email || !phone || !password || !role) {
    error = "Please fill in all required fields.";
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    error = "Please enter a valid email address.";
  } else if (password !== confirmPassword) {
    error = "Passwords do not match.";
  } else if (password.length < 6) {
    error = "Password must be at least 6 characters.";
  } else if (phone.length < 6 || phone.length > 40) {
    error = "Please enter a valid phone number.";
  } else if (!VALID_ROLES.has(role)) {
    error = "Please select a valid account type.";
  } else if (role === "mentor" && (!courseCode || !grade)) {
    error = "Mentors must select a course and grade.";
  } else if (courseCode.length > 40 || name.length > 150 || email.length > 255) {
    error = "One or more fields exceed the allowed length.";
  } else if (!agreeTerms) {
    error = "Please agree to the Terms and Conditions.";
  }

  if (error) {
    redirect(`/register?error=${encodeURIComponent(error)}`);
  }

  try {
    await registerUser({
      name,
      email,
      phone,
      password,
      role: role as "mentor" | "mentee",
      programme,
      yearOfStudy,
      courseCode,
      courseName,
      grade,
    });
  } catch (registrationError) {
    if (registrationError instanceof AuthenticationError) {
      redirect(`/register?error=${encodeURIComponent(registrationError.message)}`);
    }
    throw registrationError;
  }

  redirect("/login?registered=1");
}
