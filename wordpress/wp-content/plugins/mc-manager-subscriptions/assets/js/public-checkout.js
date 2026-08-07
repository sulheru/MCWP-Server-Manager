(() => {
  'use strict';

  const root = document.querySelector('[data-optigrid-checkout]');
  if (!root) return;

  const config = {
    ajaxUrl: root.dataset.ajaxUrl || '',
    nonce: root.dataset.nonce || '',
    pollMs: Number(root.dataset.pollMs || 3000),
    labels: {
      opening: root.dataset.labelOpening || 'Abriendo la pasarela…',
      waiting: root.dataset.labelWaiting || 'Esperando confirmación del pago…',
      popupBlocked: root.dataset.labelPopupBlocked || 'El navegador bloqueó la nueva pestaña.',
      error: root.dataset.labelError || 'No se pudo iniciar el pago.'
    }
  };

  if (!config.ajaxUrl || !config.nonce) {
    console.error('[OptiGrid Checkout] Configuración incompleta');
    return;
  }

  const modal = root.querySelector('[data-gateway-modal]');
  const statusBox = root.querySelector('[data-checkout-status]');
  let selected = null;
  let pollTimer = null;

  const showStatus = (message, state='info') => {
    if (!statusBox) return;
    statusBox.hidden = false;
    statusBox.dataset.state = state;
    statusBox.textContent = message;
  };

  const closeModal = () => {
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden','true');
  };

  const openModal = (button) => {
    selected = {
      planId: button.dataset.planId,
      planName: button.dataset.planName,
      key: button.dataset.idempotency
    };
    if (!modal) return;
    const name = modal.querySelector('[data-selected-plan-name]');
    if (name) name.textContent = selected.planName || '';
    modal.hidden = false;
    modal.setAttribute('aria-hidden','false');
  };

  async function post(data){
    const body = new URLSearchParams(data);
    const r = await fetch(config.ajaxUrl,{
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body
    });
    return r.json();
  }

  async function check(publicId){
    try{
      const json = await post({
        action:'optigrid_checkout_order_status',
        nonce:config.nonce,
        public_id:publicId
      });
      if(!json.success) return;
      const s=json.data.status;
      if(s==='pending'){
        showStatus(config.labels.waiting,'pending');
        return;
      }
      clearInterval(pollTimer);
      const labels={
        paid:'Pago aprobado. Tu suscripción está activa.',
        failed:'Pago rechazado o error técnico.',
        cancelled:'Pago cancelado.'
      };
      showStatus(labels[s]||('Estado: '+s),s==='paid'?'success':'error');
    }catch(e){
      console.warn('[OptiGrid Checkout] Error consultando estado',e);
    }
  }

  function poll(publicId){
    clearInterval(pollTimer);
    check(publicId);
    pollTimer=setInterval(()=>check(publicId),config.pollMs);
  }

  async function start(gateway){
    if(!selected) return;
    const popup=window.open('about:blank','_blank');
    if(!popup){
      showStatus(config.labels.popupBlocked,'error');
      return;
    }
    popup.document.write('<!doctype html><meta charset="utf-8"><p>'+config.labels.opening+'</p>');
    closeModal();
    showStatus(config.labels.opening,'info');
    try{
      const json=await post({
        action:'optigrid_create_checkout_order',
        nonce:config.nonce,
        plan_id:selected.planId,
        gateway,
        idempotency_key:selected.key
      });
      if(!json.success) throw new Error(json.data?.message || config.labels.error);
      popup.location=json.data.gatewayUrl;
      showStatus(config.labels.waiting,'pending');
      poll(json.data.publicId);
    }catch(e){
      popup.close();
      showStatus(e?.message || config.labels.error,'error');
    }
  }

  root.querySelectorAll('[data-select-plan]').forEach(
    b=>b.addEventListener('click',()=>openModal(b))
  );
  root.querySelectorAll('[data-close-modal]').forEach(
    b=>b.addEventListener('click',closeModal)
  );
  root.querySelectorAll('[data-gateway]').forEach(
    b=>b.addEventListener('click',()=>start(b.dataset.gateway))
  );
})();
