
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
 
// Register any Alpine directives, components, or plugins here...
 
Livewire.start()

window.navigateTo = function(url){
    document.body.style.opacity = 0;
    setTimeout(()=>location.href = url, 50);
    
    
}
