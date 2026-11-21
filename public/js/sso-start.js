document.addEventListener('DOMContentLoaded', function () {
    var link = document.getElementById('sso-start-link');
    if (!link) return;

    link.addEventListener('click', function () {
        link.classList.add('opacity-70', 'pointer-events-none');
        document.getElementById('sso-start-icon').style.display = 'none';
        document.getElementById('sso-start-spinner').style.display = 'inline-block';
        document.getElementById('sso-start-label').textContent = 'Redirigiendo…';
    });
});
