<?php
/**
 * Login API Documentation Section
 * Provides detailed documentation for authentication, responses, and session management.
 */

return [
    'id' => 'login',
    'title' => 'Login & Authentication API',
    'icon' => 'fas fa-key',
    'content' => '
        <div class="space-y-6">
            <!-- Header Summary -->
            <div class="p-4 bg-indigo-50 border-l-4 border-indigo-600 rounded-r-lg">
                <h2 class="text-lg font-bold text-indigo-900 mb-1"><i class="fas fa-sign-in-alt mr-2"></i>Authentication Overview</h2>
                <p class="text-sm text-indigo-700">
                    The ADV Clarity Management System provides a unified dual-authentication model supporting both traditional Session-based authentication and JWT (JSON Web Token) Bearer authentication.
                </p>
            </div>

            <!-- Endpoint Info -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                    <span class="px-2.5 py-1 bg-green-100 text-green-800 text-xs font-bold rounded mr-2">POST</span>
                    <code>/api/auth/login.php</code>
                </h3>
                <p class="text-sm text-gray-600 mb-4">
                    This single endpoint handles login requests for <strong>all users</strong> across the platform, including ADV Super Admins, ADV Managers, and external Contractor / Partner company users.
                </p>
                
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Request Headers</h4>
                <div class="bg-gray-800 text-gray-100 p-3 rounded-lg text-xs font-mono mb-4">
                    Content-Type: application/json<br>
                    X-Requested-With: XMLHttpRequest
                </div>

                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Request Payload (JSON or Form Data)</h4>
                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs font-mono overflow-x-auto">{
  "username": "admin_user", // or email address
  "password": "secure_password_here"
}</pre>
            </div>

            <!-- ADV vs Contractor Responses -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3"><i class="fas fa-building mr-2 text-blue-600"></i>Responses for ADV vs. Contractor Companies</h3>
                <p class="text-sm text-gray-600 mb-4">
                    The response structure is standardized for all users. The user role and company isolation scope are defined by <code>company_type</code> inside the returned <code>user</code> object:
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="p-4 border border-blue-200 bg-blue-50/50 rounded-lg">
                        <div class="flex items-center mb-2">
                            <span class="px-2 py-0.5 bg-blue-600 text-white font-bold text-[10px] rounded mr-2">ADV USER</span>
                            <span class="text-xs font-bold text-blue-900">Company Type: "ADV"</span>
                        </div>
                        <p class="text-xs text-blue-800">
                            Full system-wide access. Unrestricted view across all site delegations, companies, master registries, and system configurations.
                        </p>
                    </div>
                    <div class="p-4 border border-purple-200 bg-purple-50/50 rounded-lg">
                        <div class="flex items-center mb-2">
                            <span class="px-2 py-0.5 bg-purple-600 text-white font-bold text-[10px] rounded mr-2">CONTRACTOR</span>
                            <span class="text-xs font-bold text-purple-900">Company Type: "CONTRACTOR"</span>
                        </div>
                        <p class="text-xs text-purple-800">
                            Strictly isolated to their assigned <code>company_id</code>. Access limited to assigned delegations, contractor inventory, and field dispatch operations.
                        </p>
                    </div>
                </div>

                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">HTTP 200 Success Response Example</h4>
                <pre class="bg-gray-900 text-blue-300 p-4 rounded-lg text-xs font-mono overflow-x-auto mb-4">{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@clarity.com",
      "role": "Super Admin",
      "company": "ADV Clarity Head Office",
      "company_type": "ADV" // or "CONTRACTOR"
    },
    "redirect": "../../dashboard.php",
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "d7bb13acf8382c27c966b90e4adc587b...",
    "token_expires_at": "2026-06-28 01:24:15",
    "refresh_expires_at": "2026-07-28 00:24:15"
  }
}</pre>

                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Error Responses</h4>
                <div class="space-y-2 text-xs font-mono">
                    <div class="p-2.5 bg-red-50 text-red-700 rounded border border-red-200">
                        <strong>HTTP 401 Unauthorized:</strong> {"success": false, "message": "Authentication failed", "error": "Invalid username or password"}
                    </div>
                    <div class="p-2.5 bg-amber-50 text-amber-700 rounded border border-amber-200">
                        <strong>HTTP 400 Bad Request:</strong> {"success": false, "message": "Validation failed", "error": "Username and password are required"}
                    </div>
                </div>
            </div>

            <!-- Session Maintenance Details -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3"><i class="fas fa-database mr-2 text-emerald-600"></i>What Details to Store to Maintain Session</h3>
                
                <div class="space-y-4 text-sm text-gray-700">
                    <div>
                        <h4 class="font-bold text-gray-900 flex items-center mb-1">
                            <i class="fas fa-globe mr-2 text-blue-500"></i>1. Browser / Web Application Clients
                        </h4>
                        <p class="text-xs text-gray-600 mb-2">
                            The API automatically handles session cookies for browser clients upon successful login. No manual storage script is strictly required, as HTTP-Only cookies protect tokens from XSS:
                        </p>
                        <ul class="list-disc list-inside text-xs text-gray-600 space-y-1 pl-2">
                            <li><code>PHPSESSID</code>: Standard PHP Session cookie linked to the <code>user_sessions</code> database table.</li>
                            <li><code>adv_access_token</code>: HTTP-Only cookie storing the short-lived JWT Access Token.</li>
                            <li><code>adv_refresh_token</code>: HTTP-Only cookie storing the long-lived Refresh Token.</li>
                        </ul>
                    </div>

                    <hr class="border-gray-100">

                    <div>
                        <h4 class="font-bold text-gray-900 flex items-center mb-1">
                            <i class="fas fa-mobile-alt mr-2 text-purple-500"></i>2. REST API / SPA / Mobile Clients
                        </h4>
                        <p class="text-xs text-gray-600 mb-2">
                            API clients that do not use cookies should extract and persist the following properties from the <code>data</code> object:
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse border border-gray-200">
                                <thead class="bg-gray-50 text-gray-700 font-bold">
                                    <tr>
                                        <th class="p-2 border border-gray-200">Property</th>
                                        <th class="p-2 border border-gray-200">Storage Location</th>
                                        <th class="p-2 border border-gray-200">Usage & Lifecycle</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 font-mono text-[11px]">
                                    <tr>
                                        <td class="p-2 font-bold text-indigo-600">access_token</td>
                                        <td class="p-2">In-Memory or SessionStorage</td>
                                        <td class="p-2 font-sans text-xs">Include in all requests as header: <code>Authorization: Bearer &lt;access_token&gt;</code>. Valid for 1 hour.</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-bold text-purple-600">refresh_token</td>
                                        <td class="p-2">Encrypted Storage / Keychain</td>
                                        <td class="p-2 font-sans text-xs">Used to obtain a new access token via <code>POST /api/auth/refresh.php</code> when access token expires (HTTP 401).</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 font-bold text-gray-600">user</td>
                                        <td class="p-2">LocalStorage / Application State</td>
                                        <td class="p-2 font-sans text-xs">Store user metadata (id, username, role, company_type) for UI rendering and permission checks.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    '
];
