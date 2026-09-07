@props(['steps', 'label' => 'Secuencia de la maniobra'])
@php($states = ['pending' => 'Pendiente', 'current' => 'Paso actual', 'complete' => 'Completado', 'return' => 'Retorno pendiente', 'blocked' => 'En pausa'])
<ol {{ $attributes->class('eui-progress') }} aria-label="{{ $label }}">
    @foreach($steps as $step)
        @php($state = array_key_exists($step['state'] ?? '', $states) ? $step['state'] : 'pending')
        <li class="eui-progress__step" @if($state === 'current') aria-current="step" @endif>
            <span class="eui-progress__number" aria-hidden="true">{{ $loop->iteration }}</span>
            <span>{{ $step['label'] }}<span class="eui-progress__state">{{ $states[$state] }}</span></span>
        </li>
    @endforeach
</ol>
