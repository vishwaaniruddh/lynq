<?php
/**
 * Sites Module & API Documentation Section
 * Location: http://localhost/sites/index.php
 */

return [
    'id' => 'sites',
    'title' => 'Sites Management & API (-sites)',
    'icon' => 'fas fa-map-marker-alt',
    'content' => '
        <div class="space-y-6">
            <!-- Overview Banner -->
            <div class="p-4 bg-amber-50 border-l-4 border-amber-600 rounded-r-lg">
                <h2 class="text-lg font-bold text-amber-900 mb-1"><i class="fas fa-map-marker-alt mr-2"></i>Sites Management Overview</h2>
                <p class="text-sm text-amber-800">
                    Accessible at <code>http://localhost/sites/index.php</code>. Provides full lifecycle management for operational sites, including site search, LHO filtering, delegation, feasibility tracking, material request generation, and installation initiation.
                </p>
            </div>

            <!-- Page & Feature Components -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3"><i class="fas fa-desktop text-blue-600 mr-2"></i>Web Page Component (sites/index.php)</h3>
                <p class="text-sm text-gray-600 mb-4">
                    The frontend interface includes quick statistics cards, advanced multi-criteria filters, responsive data tables, and interactive modal dialogs.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs mb-4">
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <strong class="text-gray-800 block mb-1">📊 Stat Cards</strong>
                        Total Sites, Active Sites, Inactive Sites, and Delegated Sites counts.
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <strong class="text-gray-800 block mb-1">🔍 Filter Toolbar</strong>
                        Instant search, Status filter, LHO dropdown, Delegation status, and Material status.
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <strong class="text-gray-800 block mb-1">🛠️ Modal Dialogs</strong>
                        Add/Edit Site Modal, Site View Modal, and Material Request Modal.
                    </div>
                </div>
            </div>

            <!-- Main Listing API -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                    <span class="px-2.5 py-1 bg-blue-100 text-blue-800 text-xs font-bold rounded mr-2">GET</span>
                    <code>/api/sites/index.php</code>
                </h3>
                <p class="text-sm text-gray-600 mb-4">Fetches paginated list of sites with company isolation and multi-filter support.</p>

                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Supported Query Parameters</h4>
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-xs border-collapse border border-gray-200">
                        <thead class="bg-gray-50 text-gray-700 font-bold">
                            <tr>
                                <th class="p-2 border">Parameter</th>
                                <th class="p-2 border">Type</th>
                                <th class="p-2 border">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-mono text-[11px]">
                            <tr>
                                <td class="p-2 font-bold text-indigo-600">search</td>
                                <td class="p-2">string</td>
                                <td class="p-2 font-sans text-xs">Filter by site name, city, state, address, or bank.</td>
                            </tr>
                            <tr>
                                <td class="p-2 font-bold text-indigo-600">status</td>
                                <td class="p-2">string</td>
                                <td class="p-2 font-sans text-xs">Filter by operational status: <code>active</code> or <code>inactive</code>.</td>
                            </tr>
                            <tr>
                                <td class="p-2 font-bold text-indigo-600">lho</td>
                                <td class="p-2">string</td>
                                <td class="p-2 font-sans text-xs">Filter by Local Head Office (LHO) name.</td>
                            </tr>
                            <tr>
                                <td class="p-2 font-bold text-indigo-600">page / limit</td>
                                <td class="p-2">integer</td>
                                <td class="p-2 font-sans text-xs">Pagination controls (e.g., <code>page=1&limit=20</code>).</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Sample JSON Response</h4>
                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs font-mono overflow-x-auto">{
  "success": true,
  "message": "Sites retrieved successfully",
  "data": {
    "sites": [
      {
        "id": 101,
        "site_name": "SBI ATM MG Road",
        "lho": "Bengaluru LHO",
        "city": "Bengaluru",
        "state": "Karnataka",
        "status": "active",
        "feasibility_status": "completed",
        "installation_status": "pending"
      }
    ],
    "pagination": { "page": 1, "limit": 20, "total": 45, "total_pages": 3 }
  }
}</pre>
            </div>

            <!-- Site Mutation API -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                    <span class="px-2.5 py-1 bg-green-100 text-green-800 text-xs font-bold rounded mr-2">POST</span>
                    <code>/api/sites/index.php</code>
                </h3>
                <p class="text-sm text-gray-600 mb-4">Handles creation, updates, and deletion of site records based on the <code>action</code> parameter.</p>

                <div class="space-y-3 text-xs">
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <strong class="text-indigo-700 block mb-1">➕ Create Site (action=create)</strong>
                        Requires: <code>site_name</code>, <code>lho</code>, <code>city</code>, <code>state</code>, <code>country</code>. Prevents duplicate site names within the same LHO.
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <strong class="text-amber-700 block mb-1">✏️ Update Site (action=update)</strong>
                        Requires: <code>id</code>. Updates latitude, longitude, address, zone, and status.
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border">
                        <strong class="text-red-700 block mb-1">🗑️ Delete Site (action=delete)</strong>
                        Requires: <code>id</code>. Soft-deletes site record from active operations.
                    </div>
                </div>
            <!-- Lightweight Active Sites Fetch API -->
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                    <span class="px-2.5 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded mr-2">GET</span>
                    <code>/api/sites/fetch_sites.php</code>
                </h3>
                <p class="text-sm text-gray-600 mb-4">Lightweight API designed specifically for dropdowns and selectors. Returns <strong>only active sites</strong> (<code>status = "active"</code>) with only their <code>id</code> and <code>site_name</code> fields.</p>

                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Sample JSON Response</h4>
                <pre class="bg-gray-900 text-purple-300 p-4 rounded-lg text-xs font-mono overflow-x-auto">{
  "success": true,
  "message": "Active sites fetched successfully",
  "data": {
    "sites": [
      { "id": 101, "site_name": "SBI ATM MG Road" },
      { "id": 105, "site_name": "HDFC Indiranagar Branch" }
    ],
    "total": 2
  }
}</pre>
            </div>
        </div>
    '
];
