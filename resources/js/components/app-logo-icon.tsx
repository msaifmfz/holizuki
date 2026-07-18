import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 4H14V18H26V4H33V38H26V25H14V38H7V4Z" />
        </svg>
    );
}
