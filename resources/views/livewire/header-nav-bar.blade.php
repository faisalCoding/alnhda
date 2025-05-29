<div class=" container px-3 flex justify-end  md:justify-between items-center w-full">
    <div class=" hidden md:block *:p-5  *:cursor-pointer *:hover:border-[#498E49] *:duration-300">
        <a href='{{route('welcome')}}' wire:current="font-bold text-[#498E49]" wire:navigate.hover >الرئيسية</a>
        <a href='{{route('projects')}}' wire:current="font-bold text-[#498E49]" wire:navigate.hover >مشاريعنا</a>
        <a href='{{route('blogs')}}' wire:current="font-bold text-[#498E49]" wire:navigate.hover >المقالات</a>
    </div>
    <img src="/img/alnhdafooterlogo.png" class=" w-[200px] place-self-start" >
</div>