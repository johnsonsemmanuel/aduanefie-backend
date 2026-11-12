@props([
    'status' => 'farming',
    'size' => 'sm',
    'class' => null,
])

<?php
$labels = [
    'farming' => translate('messages.farming'),
    'harvest_ready' => translate('messages.harvest_ready'),
    'in_stock' => translate('messages.in_stock'),
    'backyard' => translate('messages.backyard_grown'),
];
$label = $labels[$status] ?? $status;

$colorMap = [
    'farming' => '#856404',
    'harvest_ready' => '#155724',
    'in_stock' => '#28a745',
    'backyard' => '#67513A',
];
$color = $colorMap[$status] ?? '#67513A';

$sizeClasses = [
    'sm' => 'farming-badge--sm',
    'md' => 'farming-badge--md',
    'lg' => 'farming-badge--lg',
];
$sizeClass = $sizeClasses[$size] ?? 'farming-badge--sm';
?>

<span class="farming-badge {{ $sizeClass }} {{ $class }}"
      style="background-color: {{ $color }};">
    {{ $label }}
</span>

<style>
    .farming-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .farming-badge--sm {
        font-size: 10px;
        padding: 3px 8px;
    }

    .farming-badge--md {
        font-size: 12px;
        padding: 5px 12px;
    }

    .farming-badge--lg {
        font-size: 14px;
        padding: 6px 14px;
    }

    .farming-badge i {
        font-size: 0.85em;
    }
</style>
