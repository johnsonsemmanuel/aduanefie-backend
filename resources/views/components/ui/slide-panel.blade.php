@props([
    'step' => 1,
    'totalSteps' => 2,
    'stepLabels' => ['Sign In', 'Two-Factor'],
    'onNext' => null,
    'onBack' => null,
])

<div class="slide-panel-container">
    <!-- Step indicator -->
    <div class="slide-steps">
        @for($i = 1; $i <= $totalSteps; $i++)
            <div class="slide-step {{ $i <= $step ? 'active' : '' }} {{ $i === $step ? 'current' : '' }}"
                 data-step="{{ $i }}">
                <span class="step-number">{{ $i }}</span>
                <span class="step-label">{{ $stepLabels[$i - 1] ?? '' }}</span>
            </div>
            @if($i < $totalSteps)
                <div class="step-connector"></div>
            @endif
        @endfor
    </div>

    <!-- Sliding panels -->
    <div class="slide-panels">
        @for($i = 1; $i <= $totalSteps; $i++)
            <div class="slide-panel {{ $i === $step ? 'active' : '' }}"
                 id="slide-panel-{{ $i }}"
                 style="transform: translateX({{ ($i - 1) * -100 }}%);">
                {{ $i === 1 ? $step1Content ?? '' : '' }}
                {{ $i === 2 ? $step2Content ?? '' : '' }}
            </div>
        @endfor
    </div>
</div>

<style>
    .slide-panel-container {
        max-width: 460px;
        margin: 0 auto;
    }

    .slide-steps {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        position: relative;
    }

    .slide-step {
        display: flex;
        align-items: center;
        position: relative;
        z-index: 2;
    }

    .step-number {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #E0D6BD;
        color: #67513A;
        font-weight: 700;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
        transition: all 0.3s ease;
    }

    .slide-step.active .step-number {
        background: var(--primary-clr, #67513A);
        color: #fff;
    }

    .step-label {
        font-size: 13px;
        font-weight: 600;
        color: #67513A;
        opacity: 0.5;
        transition: opacity 0.3s ease;
    }

    .slide-step.active .step-label {
        opacity: 1;
    }

    .step-connector {
        flex: 1;
        height: 2px;
        background: #E0D6BD;
        margin: 0 8px;
        position: relative;
        z-index: 1;
    }

    .slide-panels {
        display: flex;
        width: 100%;
        overflow: hidden;
        position: relative;
        height: auto;
        min-height: 400px;
    }

    .slide-panel {
        flex: 0 0 100%;
        width: 100%;
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: transform;
        padding: 20px 0;
    }

    .slide-panel.active {
        position: relative;
        z-index: 2;
    }

    @media (max-width: 576px) {
        .slide-step {
            font-size: 0;
        }
        .step-label {
            font-size: 11px;
        }
    }
</style>
