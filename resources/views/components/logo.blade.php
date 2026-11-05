<svg {{ $attributes->merge(['viewBox' => '0 0 16 16', 'xmlns' => 'http://www.w3.org/2000/svg', 'width' => '32', 'height' => '32', 'aria-label' => 'assertchris logo']) }} id="site-logo">
    <style>
        #site-logo {
            --logo-stroke-start: var(--color-stone-700);
            --logo-stroke-end: var(--color-stone-900);
        }
        :where(.dark) #site-logo {
            --logo-stroke-start: var(--color-stone-300);
            --logo-stroke-end: var(--color-stone-100);
        }
        #site-logo path {
            stroke-width: 0.75;
            stroke: var(--logo-stroke-start);
            animation: logo-animation 3s linear 1s forwards;
        }
        @@keyframes logo-animation {
            to {
                stroke: var(--logo-stroke-end);
                stroke-dashoffset: 0;
            }
        }
        #site-logo path:first-child {
            stroke-dashoffset: 38.080543518066406;
            stroke-dasharray: 38.080543518066406;
        }
        #site-logo path:last-child {
            stroke-dashoffset: 38.030670166015625;
            stroke-dasharray: 38.030670166015625;
        }
    </style>
    <g>
        <path d="M7 8 3 15 1 15 9 1 11 1 7 8 6.549 8.797Z" fill="none" />
        <path d="M9 8 13 1 15 1 11 8 15 15 13 15 9 8 9.441 7.226Z" fill="none" />
    </g>
</svg>
