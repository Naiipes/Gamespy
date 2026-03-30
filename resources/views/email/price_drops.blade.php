@if ($type === 'target_price')
The price of {{ $title }} has dropped under your desired price: ${{ number_format((float) $target, 2) }}<br>
@else
{{ $title }} is now on sale for ${{ number_format((float) $price, 2) }}.<br>
@endif

Current price: ${{ number_format((float) $price, 2) }}<br>

Store: {{ $store }}
