"use server";

import { redirect } from "next/navigation";
import { AuthenticationError, authenticateUser } from "@/lib/auth";

export async function loginAction(formData: FormData): Promise<void> {
  const email = String(formData.get("email") ?? "").trim();
  const password = String(formData.get("password") ?? "");

  if (!email || !password) {
    redirect("/login?error=Please%20fill%20in%20all%20fields");
  }

  try {
    const user = await authenticateUser(email, password);
    if (user.status !== "active") {
      redirect("/dashboard");
    }
    redirect(`/${user.role}/dashboard`);
  } catch (error) {
    if (error instanceof AuthenticationError) {
      redirect(`/login?error=${encodeURIComponent(error.message)}`);
    }
    throw error;
  }
}

export async function logoutAction(): Promise<void> {
  const { signOut } = await import("@/lib/auth");
  await signOut();
  redirect("/");
}
