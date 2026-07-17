document.querySelectorAll('.curriculum-section').forEach(section=>section.querySelector('header')?.addEventListener('click',()=>section.classList.toggle('collapsed')));
