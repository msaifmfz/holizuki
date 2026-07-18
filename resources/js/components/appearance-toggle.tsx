import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';

const options: Array<{
    value: Appearance;
    label: string;
    icon: typeof Sun;
}> = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

export default function AppearanceToggle() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Change theme">
                    <Sun className="dark:hidden" />
                    <Moon className="hidden dark:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map(({ value, label, icon: Icon }) => (
                    <DropdownMenuItem
                        key={value}
                        onClick={() => updateAppearance(value)}
                    >
                        <Icon />
                        {label}
                        {appearance === value && <Check className="ml-auto" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
