<div class="position-relative overflow-hidden rounded bg-secondary-subtle border" style="height:22px" role="img" aria-label="{{ $etiqueta ?? 'Periodo asignado' }}. Tiempo transcurrido {{ round($barra['elapsed_percent']) }} por ciento. Rojo: reparación registrada.">
    <div class="position-absolute top-0 bottom-0 start-0 bg-info opacity-50" style="width:{{ $barra['elapsed_percent'] }}%"></div>
    @foreach($barra['segments'] as $tramo)
        <div class="position-absolute top-0 bottom-0 bg-danger" style="left:{{ $tramo['from'] }}%;width:{{ $tramo['to'] - $tramo['from'] }}%" title="Intervalo en reparación"></div>
    @endforeach
</div>
