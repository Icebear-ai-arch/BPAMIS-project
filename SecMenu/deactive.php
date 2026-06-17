<?php
// Expects $account to be present in scope with keys: account_type, id, isActive, email, name
if (!isset($account)) return;
$type = $account['account_type'];
$id = $account['id'];
$active = !empty($account['isActive']);
?>
<div class="flex items-center gap-2">
    <?php if ($active): ?>
        <button id="deactivateBtn" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-500 text-white text-sm font-medium shadow-sm">Deactivate</button>
    <?php else: ?>
        <!-- Activation uses the existing activation flow which expects an email; submit non-AJAX form -->
        <form method="post" action="../controllers/account_activate.php" onsubmit="return confirm('Send activation email and activate this account?');">
            <input type="hidden" name="activation_email" value="<?= htmlspecialchars($account['email']) ?>" />
            <button type="submit" name="submitActivation" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium shadow-sm">Activate</button>
        </form>
    <?php endif; ?>
</div>

<!-- Deactivation Modal -->
<div id="deactModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-lg w-full p-6">
        <h3 class="text-lg font-semibold mb-3">Deactivate Account</h3>
        <p class="text-sm text-gray-600 mb-3">Provide a reason for deactivating the account for audit and notification.</p>
        <textarea id="deactReason" class="w-full border rounded p-2 mb-4" rows="4" placeholder="Reason for deactivation"></textarea>
        <div class="flex justify-end gap-2">
            <button id="deactCancel" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
            <button id="deactConfirm" class="px-4 py-2 rounded bg-rose-600 text-white">Confirm Deactivate</button>
        </div>
    </div>
</div>

<script>
(function(){
    const btn = document.getElementById('deactivateBtn');
    const modal = document.getElementById('deactModal');
    const cancel = document.getElementById('deactCancel');
    const confirm = document.getElementById('deactConfirm');
    const reasonEl = document.getElementById('deactReason');
    if(btn){
        btn.addEventListener('click', ()=>{ modal.classList.remove('hidden'); modal.classList.add('flex'); });
    }
    if(cancel){ cancel.addEventListener('click', ()=>{ modal.classList.add('hidden'); modal.classList.remove('flex'); }); }
    if(confirm){
        confirm.addEventListener('click', async ()=>{
            const reason = reasonEl.value.trim();
            if(!reason){ alert('Please provide a reason for deactivation.'); return; }
            confirm.disabled = true;
            confirm.textContent = 'Processing...';
            try{
                const resp = await fetch('../controllers/account_deactivation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ deactivate: '1', id: '<?= $id ?>', type: '<?= $type ?>', reason: reason, ajax: '1' })
                });
                const data = await resp.json();
                if(data.success){
                    alert(data.message || 'Account deactivated');
                    window.location.href = 'home-secretary.php';
                } else {
                    alert(data.message || 'Failed to deactivate account');
                    confirm.disabled = false;
                    confirm.textContent = 'Confirm Deactivate';
                }
            }catch(e){
                alert('Request failed: '+e.message);
                confirm.disabled = false;
                confirm.textContent = 'Confirm Deactivate';
            }
        });
    }
})();
</script>
