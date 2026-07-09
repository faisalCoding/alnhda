window.navigateTo = function(url){
    document.body.style.opacity = 0;
    setTimeout(()=>location.href = url, 50);
}

window.addEventListener('pageshow', () => {
    document.body.style.opacity = '';
});
