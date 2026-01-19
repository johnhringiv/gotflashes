<div>
    @if($shouldShow)
        <div id="verification-banner" class="alert alert-warning shadow-lg" role="alert" data-cooldown="{{ $cooldownSeconds }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h3 class="font-bold">Please verify your email address</h3>
                <div class="text-sm">
                    <span data-cooldown-message style="{{ $cooldownSeconds > 0 ? '' : 'display: none;' }}">A verification email was just sent. Please check your inbox (and spam folder).</span>
                    <span data-default-message style="{{ $cooldownSeconds > 0 ? 'display: none;' : '' }}">You won't receive award notifications or important updates until you verify.</span>
                </div>
            </div>
            <div class="flex-none">
                <span data-cooldown-btn class="btn btn-sm btn-ghost btn-disabled cursor-not-allowed" style="{{ $cooldownSeconds > 0 ? '' : 'display: none;' }}">
                    Resend in <span data-time data-countdown>{{ floor($cooldownSeconds / 60) > 0 ? floor($cooldownSeconds / 60) . ':' . str_pad($cooldownSeconds % 60, 2, '0', STR_PAD_LEFT) : $cooldownSeconds . 's' }}</span>
                </span>
                <button data-resend-btn wire:click="resendVerification" class="btn btn-sm btn-ghost" wire:loading.attr="disabled" wire:target="resendVerification" style="{{ $cooldownSeconds > 0 ? 'display: none;' : '' }}">
                    <span wire:loading.remove wire:target="resendVerification">Resend Verification Email</span>
                    <span wire:loading wire:target="resendVerification">
                        <span class="loading loading-spinner loading-xs"></span>
                        Sending...
                    </span>
                </button>
            </div>
        </div>
    @endif
</div>
