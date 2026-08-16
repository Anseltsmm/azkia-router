<?php

namespace App\Models;

use DOMDocument;
use DOMElement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'body', 'type', 'has_shine_effect', 'button_text', 'button_url', 'starts_at', 'ends_at', 'is_active'])]
class DashboardPopup extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'has_shine_effect' => 'boolean',
        ];
    }

    public function scopeCurrentlyActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public static function sanitizeBody(string $html): string
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $allowedTags = ['div', 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'span'];
        $allowedStyles = ['color', 'font-size', 'text-align'];
        $nodes = iterator_to_array($document->getElementsByTagName('*'));

        foreach ($nodes as $node) {
            if (! in_array(strtolower($node->tagName), $allowedTags, true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                if ($attribute->name === 'href' && $node->tagName === 'a') {
                    $href = trim($attribute->value);
                    if (! preg_match('/^https?:\/\//i', $href)) {
                        $node->removeAttribute('href');
                    } else {
                        $node->setAttribute('target', '_blank');
                        $node->setAttribute('rel', 'noopener noreferrer');
                    }
                } elseif ($attribute->name === 'class' && $node->tagName === 'span') {
                    $classes = collect(preg_split('/\s+/', $attribute->value) ?: [])
                        ->filter(fn ($class) => $class === 'popup-shine')
                        ->implode(' ');
                    $classes ? $node->setAttribute('class', $classes) : $node->removeAttribute('class');
                } elseif ($attribute->name === 'style') {
                    $styles = collect(explode(';', $attribute->value))
                        ->map(fn ($style) => array_map('trim', explode(':', $style, 2)))
                        ->filter(fn ($parts) => count($parts) === 2 && in_array(strtolower($parts[0]), $allowedStyles, true))
                        ->filter(fn ($parts) => preg_match('/^[#(),.%\-\w\s]+$/', $parts[1]))
                        ->map(fn ($parts) => strtolower($parts[0]).': '.$parts[1])
                        ->implode('; ');
                    $styles ? $node->setAttribute('style', $styles) : $node->removeAttribute('style');
                } elseif (! in_array($attribute->name, ['target', 'rel'], true)) {
                    $node->removeAttribute($attribute->name);
                }
            }
        }

        $wrapper = $document->getElementsByTagName('div')->item(0);
        if (! $wrapper instanceof DOMElement) {
            return '';
        }

        return collect(iterator_to_array($wrapper->childNodes))
            ->map(fn ($node) => $document->saveHTML($node))
            ->implode('');
    }
}
