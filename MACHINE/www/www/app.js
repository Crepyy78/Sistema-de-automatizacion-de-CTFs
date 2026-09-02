/* ------------------------------------------------------------------
 * CONFIG — point these at your actual endpoints.
 * Adjust the field names in parseApiResponse() below if your API's
 * JSON shape differs from the guesses made here.
 * ---------------------------------------------------------------- */
const API_ORIGIN = `${window.location.protocol}//${window.location.hostname}:8001`;

const CONFIG = {
  LOGIN_URL: `${API_ORIGIN}/login.php`,
  REGISTER_URL: `${API_ORIGIN}/register.php`,
  HUB_URL: `${API_ORIGIN}/hub.php`,
  CHECK_SESSION_URL: `${API_ORIGIN}/checkSession.php`,
  MANAGE_USERS_URL: `${API_ORIGIN}/manageUsers.php`,
  MANAGE_TEAMS_URL: `${API_ORIGIN}/manageTeam.php`,
  CREATE_TEAM_INVITE_URL: `${API_ORIGIN}/createInvitanionalCodeTeam.php`,
  USE_TEAM_INVITE_URL: `${API_ORIGIN}/useInvitationalCodeTeam.php`,
  DELETE_TEAM_MEMBER_URL: `${API_ORIGIN}/deleteUserFromTeam.php`,
  PROFILE_URL: `${API_ORIGIN}/register.php`,
  CREATE_EVENT_URL: `${API_ORIGIN}/createEventCTF.php`,
  MANAGE_STATUS_CHALLENGE_URL: `${API_ORIGIN}/manageStatusChallenge.php`,
  ADMINISTRATE_CTF_URL: `${API_ORIGIN}/administrateCTF.php`,
  GET_CHALLENGES_FOR_CTF_URL: `${API_ORIGIN}/getChallengesForCTF.php`,
  CREATE_EVENT_INVITE_URL: `${API_ORIGIN}/createInvitationalCodeEvent.php`,
  USE_EVENT_INVITE_URL: `${API_ORIGIN}/useInvitationalCodeEvent.php`,
  JOIN_EVENT_URL: `${API_ORIGIN}/joinEventCTF.php`,
  SUBMIT_FLAG_URL: `${API_ORIGIN}/submitFlagCTF.php`,
  GET_RANKING_URL: `${API_ORIGIN}/getRankingCTF.php`,
  GET_GLOBAL_RANKING_URL: `${API_ORIGIN}/getGlobalRankingCTF.php`,
  // Where to send the user after a successful login.
  AFTER_LOGIN_URL: "dashboard.html",
};

/**
 * Posts to a PHP endpoint as classic application/x-www-form-urlencoded
 * fields (i.e. what $_POST['username'] etc. expects). Switch the
 * Content-Type / body below to JSON.stringify(...) if your API
 * expects a JSON body instead.
 */
async function postForm(url, fields) {
  const body = new URLSearchParams();
  Object.entries(fields).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== "") body.set(k, v);
  });

  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString(),
    // Cross-origin (different port = different origin) — needed if the
    // API sets a session cookie. Requires the server to send
    // Access-Control-Allow-Credentials: true and an exact-match
    // Access-Control-Allow-Origin (not "*").
    credentials: "include",
  });

  let data = null;
  try {
    data = await res.json();
  } catch (_) {
    // Non-JSON response — fall back to raw text so it's not silently lost.
    data = { raw: await res.text().catch(() => "") };
  }

  return { ok: res.ok, status: res.status, data };
}

/**
 * Normalizes a handful of common API response shapes so the UI code
 * doesn't need to guess. Tweak the key names here to match your API
 * exactly once you can see a real response.
 */
function parseApiResponse(result) {
  const d = result.data || {};
  const success = d.code === "ok";

  const message = d.message || d.msg || d.error || null;

  return {
    success,
    message,
    otpauth: d.KeyURI || d.otpauth || d.otpauth_url || d.totp_uri || null,
    secret: d.secret || d.totp_secret || extractSecretFromOtpauth(d.KeyURI || d.otpauth || d.otpauth_url),
    token: d.token || d.session_token || null,
    requiresTotp: d.code === "totp_required",
  };
}

function extractSecretFromOtpauth(uri) {
  if (!uri) return null;
  try {
    const match = uri.match(/[?&]secret=([^&]+)/i);
    return match ? decodeURIComponent(match[1]) : null;
  } catch (_) {
    return null;
  }
}

function showMsg(el, text, kind) {
  el.textContent = text;
  el.className = "msg is-visible " + (kind === "good" ? "msg--good" : "msg--bad");
}

function hideMsg(el) {
  el.className = "msg";
}

/**
 * DELETE a session-authenticated endpoint (e.g. logging out via
 * login.php). Same CORS requirements as postForm()/getJson().
 */
async function deleteRequest(url) {
  const res = await fetch(url, {
    method: "DELETE",
    credentials: "include",
  });

  let data = null;
  try {
    data = await res.json();
  } catch (_) {
    data = { raw: await res.text().catch(() => "") };
  }

  return { ok: res.ok, status: res.status, data };
}

/**
 * POST a JSON body to a session-authenticated endpoint (e.g. creating
 * a team via manageTeams.php). Distinct from postForm() above, which
 * sends application/x-www-form-urlencoded for login/register.
 */
async function postJson(url, payload) {
  const res = await fetch(url, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  let data = null;
  try {
    data = await res.json();
  } catch (_) {
    data = { raw: await res.text().catch(() => "") };
  }

  return { ok: res.ok, status: res.status, data };
}

/**
 * DELETE with a JSON body (e.g. removing a user via manageUsers.php).
 * Distinct from deleteRequest() above, which sends no body — used for
 * cookie-only actions like logging out.
 */
async function deleteJson(url, payload) {
  const res = await fetch(url, {
    method: "DELETE",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  let data = null;
  try {
    data = await res.json();
  } catch (_) {
    data = { raw: await res.text().catch(() => "") };
  }

  return { ok: res.ok, status: res.status, data };
}

/**
 * PUT a JSON body to a session-authenticated endpoint (e.g.
 * manageUsers.php). Only include keys you actually want to send —
 * omit optional fields entirely rather than sending null/empty.
 */
async function putJson(url, payload) {
  const res = await fetch(url, {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  let data = null;
  try {
    data = await res.json();
  } catch (_) {
    data = { raw: await res.text().catch(() => "") };
  }

  return { ok: res.ok, status: res.status, data };
}

function setBusy(button, busy, busyLabel, idleLabel) {
  button.disabled = busy;
  button.textContent = busy ? busyLabel : idleLabel;
}

/**
 * GET a JSON endpoint with cookies attached (for session-authenticated
 * routes like hub.php). Same CORS requirements as postForm() apply:
 * the server must send an exact-match Access-Control-Allow-Origin and
 * Access-Control-Allow-Credentials: true.
 */
async function getJson(url) {
  const res = await fetch(url, {
    method: "GET",
    credentials: "include",
  });

  let data = null;
  try {
    data = await res.json();
  } catch (_) {
    data = { raw: await res.text().catch(() => "") };
  }

  return { ok: res.ok, status: res.status, data };
}