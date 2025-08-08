(function(){
  if (!document.body) return;

  function makeOpenButton(){
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='func-fs-btn button';
    btn.textContent='Fullscreen';
    btn.setAttribute('aria-label','Enter fullscreen');
    return btn;
  }
  function makeCloseButton(){
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='func-fs-close button button-primary';
    btn.textContent='Exit Fullscreen';
    btn.setAttribute('aria-label','Exit fullscreen');
    return btn;
  }

  function closeAnyActive(){
    const active=document.querySelector('.func-fs-active');
    if(!active) return;
    closeFs(active);
  }

  function openFs(wrap){
    // Close any other active instance first
    const current=document.querySelector('.func-fs-active');
    if(current && current!==wrap){ closeFs(current); }
    document.documentElement.classList.add('func-fs-open');
    wrap.classList.add('func-fs-active');
    const ta=wrap.querySelector('textarea');
    if(ta){ ta.focus(); }
  }
  function closeFs(wrap){
    wrap.classList.remove('func-fs-active');
    // If none active, drop the root flag
    if(!document.querySelector('.func-fs-active')){
      document.documentElement.classList.remove('func-fs-open');
    }
  }

  function wrapTextarea(ta){
    if(ta.dataset.funcFsInit) return;
    ta.dataset.funcFsInit='1';
    const wrap=document.createElement('div');
    wrap.className='func-fs-wrap';
    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(ta);
    const openBtn=makeOpenButton();
    ta.insertAdjacentElement('beforebegin', openBtn);
    const closeBtn=makeCloseButton();
    // place close button inside wrap so it benefits from z-index scope
    wrap.appendChild(closeBtn);

    openBtn.addEventListener('click',()=> openFs(wrap));
    closeBtn.addEventListener('click',()=> closeFs(wrap));
  }

  function onKeydown(e){
    if(e.key==='Escape' || e.key==='Esc'){
      const active=document.querySelector('.func-fs-active');
      if(active){
        e.preventDefault();
        closeFs(active);
      }
    }
  }

  function init(){
    document.querySelectorAll('textarea').forEach(wrapTextarea);
    document.addEventListener('keydown', onKeydown);
  }
  document.addEventListener('DOMContentLoaded', init);
})();
