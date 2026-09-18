const buttons = document.querySelectorAll('.filter-btn');

buttons.forEach(button => {
    button.addEventListener('click', () => {

        const parent = button.parentElement;

        parent.querySelectorAll('.filter-btn').forEach(btn=>{
            btn.classList.remove('active');
        });

        button.classList.add('active');
    });
});