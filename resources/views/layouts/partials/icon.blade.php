@php
    $name = $name ?? 'circle';
    $class = $class ?? 'ui-icon';
    $glifos = [
        'bell' => 'fa-bell',
        'chevron-down' => 'fa-chevron-down',
        'trending-up' => 'fa-arrow-trend-up',
        'wallet' => 'fa-wallet',
        'credit-card' => 'fa-credit-card',
        'piggy-bank' => 'fa-piggy-bank',
        'banknote' => 'fa-money-bill',
        'calendar' => 'fa-calendar-days',
        'arrow-up' => 'fa-arrow-up',
        'arrow-down' => 'fa-arrow-down',
        'log-out' => 'fa-right-from-bracket',
        'chart' => 'fa-chart-column',
        'house' => 'fa-house',
        'exchange' => 'fa-right-left',
        'list' => 'fa-list',
        'bullseye' => 'fa-bullseye',
        'grip' => 'fa-table-cells',
        'plus' => 'fa-plus',
        'percent' => 'fa-percent',
        'file-invoice' => 'fa-file-invoice-dollar',
        'chart-line' => 'fa-chart-line',
        'chevron-left' => 'fa-chevron-left',
        'circle' => 'fa-circle',
        'archive' => 'fa-box-archive',
        'restore' => 'fa-rotate-left',
        'ban' => 'fa-ban',
        'trash' => 'fa-trash-can',
        'check' => 'fa-check',
        'money' => 'fa-money-bill-transfer',
    ];
    $glifo = $glifos[$name] ?? $glifos['circle'];
@endphp
<i class="fa-solid {{ $glifo }} {{ $class }}" aria-hidden="true"></i>
