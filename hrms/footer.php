  </main>
   <footer class="footer">
  <small>
    &copy; <?= date('Y') ?> <?= htmlspecialchars($_SESSION['company_name'] ?? 'Boketto Technologies Pvt. Ltd.') ?>
    
  </small>
</footer>

</div><!-- /.layout -->

<script>
(function(){
  const KEY = 'sidebar.open.sections';
  function getOpen(){ try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch(e){ return []; } }
  function setOpen(arr){ localStorage.setItem(KEY, JSON.stringify(arr)); }

  window.toggleMenu = function(el){
    const sec   = el.parentElement;
    const items = el.nextElementSibling;
    const caret = el.querySelector('.caret');
    let   key   = sec.getAttribute('data-key');
    if (!key) { key = 'sec-' + Math.random().toString(36).slice(2); sec.setAttribute('data-key', key); }

    const open = getOpen();
    const idx  = open.indexOf(key);
    const willOpen = items.style.display !== 'block';

    items.style.display = willOpen ? 'block' : 'none';
    caret && caret.classList.toggle('open', willOpen);

    if (willOpen && idx === -1) { open.push(key); setOpen(open); }
    if (!willOpen && idx !== -1) { open.splice(idx,1); setOpen(open); }
  };

  // restore state + auto-open section with active link
  document.addEventListener('DOMContentLoaded', function(){
    const open = getOpen();
    document.querySelectorAll('.menu-section').forEach(sec=>{
      const key   = sec.getAttribute('data-key');
      const items = sec.querySelector('.menu-items');
      const caret = sec.querySelector('.caret');
      const hasActive = !!sec.querySelector('.menu-items a.active');

      if (hasActive || (key && open.indexOf(key) !== -1)) {
        items.style.display = 'block';
        caret && caret.classList.add('open');
      }
    });
  });
})();
</script>
</body>
</html>
