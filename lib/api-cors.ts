export function withApiCors(response: Response, methods: string): Response {
  const headers = new Headers(response.headers);
  headers.set("Access-Control-Allow-Origin", "*");
  headers.set("Access-Control-Allow-Methods", methods);
  headers.set("Access-Control-Allow-Headers", "Content-Type, Authorization");
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

export function apiOptions(methods: string): Response {
  return withApiCors(new Response(null, { status: 204 }), methods);
}
