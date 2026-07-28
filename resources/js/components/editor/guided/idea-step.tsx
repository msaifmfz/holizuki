import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    AuthoringBrief,
    AuthoringPersonality,
    AuthoringJourneyData,
} from '@/types';

const personalities: Array<{
    value: AuthoringPersonality;
    title: string;
    description: string;
}> = [
    {
        value: 'friendly',
        title: 'Friendly',
        description: 'Warm, encouraging, and easy to understand.',
    },
    {
        value: 'professional',
        title: 'Professional',
        description: 'Clear, polished, and focused on credibility.',
    },
    {
        value: 'creative',
        title: 'Creative',
        description: 'Expressive, curious, and willing to explore.',
    },
    {
        value: 'custom',
        title: 'Custom',
        description: 'Describe exactly how the assistant should write.',
    },
];

export default function IdeaStep({
    journey,
    generating,
    assistantAvailable,
    savingStyleAsDefault,
    onBriefChange,
    onPersonalityChange,
    onCustomInstructionsChange,
    onSaveStyleAsDefaultChange,
    onGeneratePlans,
}: {
    journey: AuthoringJourneyData;
    generating: boolean;
    assistantAvailable: boolean;
    savingStyleAsDefault: boolean;
    onBriefChange: (brief: AuthoringBrief) => void;
    onPersonalityChange: (personality: AuthoringPersonality) => void;
    onCustomInstructionsChange: (instructions: string) => void;
    onSaveStyleAsDefaultChange: (saveAsDefault: boolean) => void;
    onGeneratePlans: () => void;
}) {
    const updateBrief = <Key extends keyof AuthoringBrief>(
        key: Key,
        value: AuthoringBrief[Key],
    ): void => {
        onBriefChange({ ...journey.brief, [key]: value });
    };

    return (
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <Card>
                <CardHeader>
                    <CardTitle>What do you want to write about?</CardTitle>
                    <CardDescription>
                        A rough idea is enough. Add only what you already know.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="authoring-topic">Your idea</Label>
                        <Textarea
                            id="authoring-topic"
                            rows={5}
                            maxLength={10_000}
                            value={journey.brief.topic}
                            placeholder="Explain the article in your own words…"
                            onChange={(event) =>
                                updateBrief('topic', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="authoring-audience">
                                Who is it for?
                            </Label>
                            <Input
                                id="authoring-audience"
                                maxLength={500}
                                value={journey.brief.audience}
                                placeholder="Beginners, customers, peers…"
                                onChange={(event) =>
                                    updateBrief('audience', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="authoring-purpose">
                                What should readers get from it?
                            </Label>
                            <Input
                                id="authoring-purpose"
                                maxLength={500}
                                value={journey.brief.purpose}
                                placeholder="Learn, decide, try, understand…"
                                onChange={(event) =>
                                    updateBrief('purpose', event.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="authoring-key-points">
                            Notes or must-include points
                        </Label>
                        <Textarea
                            id="authoring-key-points"
                            rows={4}
                            maxLength={20_000}
                            value={journey.brief.key_points}
                            placeholder="Names, examples, opinions, facts, or reminders…"
                            onChange={(event) =>
                                updateBrief('key_points', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="authoring-length">
                            Approximate length
                        </Label>
                        <Select
                            value={journey.brief.desired_length}
                            onValueChange={(value) =>
                                updateBrief(
                                    'desired_length',
                                    value as AuthoringBrief['desired_length'],
                                )
                            }
                        >
                            <SelectTrigger
                                id="authoring-length"
                                className="w-full sm:w-64"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="short">
                                    Short — under 700 words
                                </SelectItem>
                                <SelectItem value="medium">
                                    Medium — 700–1,500 words
                                </SelectItem>
                                <SelectItem value="long">
                                    In-depth — over 1,500 words
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            <div className="grid content-start gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Choose a personality
                        </CardTitle>
                        <CardDescription>
                            This controls how the assistant talks and writes in
                            this article.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {personalities.map((personality) => (
                            <button
                                key={personality.value}
                                type="button"
                                aria-pressed={
                                    journey.personality === personality.value
                                }
                                className={cn(
                                    'grid gap-0.5 rounded-lg border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    journey.personality === personality.value
                                        ? 'border-primary bg-primary/10'
                                        : 'hover:bg-muted/60',
                                )}
                                onClick={() =>
                                    onPersonalityChange(personality.value)
                                }
                            >
                                <span className="text-sm font-medium">
                                    {personality.title}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {personality.description}
                                </span>
                            </button>
                        ))}
                        {journey.personality === 'custom' && (
                            <div className="grid gap-2 pt-2">
                                <Label htmlFor="authoring-custom-personality">
                                    Custom writing instructions
                                </Label>
                                <Textarea
                                    id="authoring-custom-personality"
                                    rows={4}
                                    maxLength={2_000}
                                    value={journey.custom_instructions}
                                    placeholder="For example: calm, practical, use short sentences, avoid jargon…"
                                    onChange={(event) =>
                                        onCustomInstructionsChange(
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        )}
                        <Label className="flex items-start gap-2 pt-2 text-xs font-normal text-muted-foreground">
                            <Checkbox
                                checked={savingStyleAsDefault}
                                onCheckedChange={(checked) =>
                                    onSaveStyleAsDefaultChange(checked === true)
                                }
                            />
                            Also use this style as the starting point for new
                            articles
                        </Label>
                    </CardContent>
                </Card>

                <Button
                    type="button"
                    size="lg"
                    disabled={
                        journey.brief.topic.trim() === '' ||
                        generating ||
                        !assistantAvailable
                    }
                    onClick={onGeneratePlans}
                >
                    <Sparkles />
                    {generating ? 'Preparing ideas…' : 'Ask AI for three plans'}
                </Button>
                {!assistantAvailable && (
                    <p className="text-sm text-muted-foreground">
                        The assistant is unavailable, but you can continue and
                        write manually.
                    </p>
                )}
            </div>
        </div>
    );
}
