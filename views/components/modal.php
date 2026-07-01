<div id="modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full relative z-10 transform transition-all">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800"></h3>
                <button onclick="closeModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modal-body" class="p-5"></div>
            <div id="modal-footer" class="flex justify-end space-x-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl"></div>
        </div>
    </div>
</div>

<div id="confirm-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full relative z-10 transform transition-all">
            <div class="p-8 text-center">
                <div id="confirm-icon" class="mx-auto mb-5 w-16 h-16 rounded-2xl flex items-center justify-center bg-red-100">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
                <h3 id="confirm-title" class="text-xl font-semibold text-gray-800 mb-2"></h3>
                <p id="confirm-message" class="text-gray-500 mb-8"></p>
                <div class="flex justify-center space-x-3">
                    <button onclick="closeConfirmModal()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button id="confirm-btn" class="px-6 py-2.5 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition shadow-lg shadow-red-600/30">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(title, body, footer = '') {
    document.getElementById('modal-title').innerHTML = title;
    document.getElementById('modal-body').innerHTML = body;
    document.getElementById('modal-footer').innerHTML = footer;
    document.getElementById('modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
    document.body.style.overflow = '';
}

function openConfirmModal(title, message, onConfirm, type = 'danger') {
    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-message').textContent = message;
    
    const icon = document.getElementById('confirm-icon');
    const btn = document.getElementById('confirm-btn');
    
    if (type === 'danger') {
        icon.className = 'mx-auto mb-5 w-16 h-16 rounded-2xl flex items-center justify-center bg-red-100';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>';
        btn.className = 'px-6 py-2.5 bg-red-600 text-white rounded-xl font-medium hover:bg-red-700 transition shadow-lg shadow-red-600/30';
    } else if (type === 'warning') {
        icon.className = 'mx-auto mb-5 w-16 h-16 rounded-2xl flex items-center justify-center bg-yellow-100';
        icon.innerHTML = '<i class="fas fa-exclamation-circle text-2xl text-yellow-600"></i>';
        btn.className = 'px-6 py-2.5 bg-yellow-600 text-white rounded-xl font-medium hover:bg-yellow-700 transition shadow-lg shadow-yellow-600/30';
    }
    
    btn.onclick = function() {
        closeConfirmModal();
        if (typeof onConfirm === 'function') onConfirm();
    };
    
    document.getElementById('confirm-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    document.getElementById('confirm-modal').classList.add('hidden');
    document.body.style.overflow = '';
}
</script>
