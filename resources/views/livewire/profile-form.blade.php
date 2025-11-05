<div class="card w-full max-w-2xl bg-base-100 mx-auto">
    <div class="card-body">
        <h1 class="text-3xl font-bold text-center mb-6">Edit Profile</h1>

        <form wire:submit="save">
            <x-user-profile-fields>
                <x-slot:passwordFields>
                    @if($hasPendingEmail)
                        <!-- Pending Email Change Notice -->
                        <div class="alert alert-info mt-3 -mt-3 mb-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="flex-1">
                                <h4 class="font-bold">Pending Email Change</h4>
                                <p class="text-sm">Waiting for verification: <strong>{{ $pendingEmail }}</strong></p>
                                <p class="text-xs mt-1">Check your new email inbox and click the verification link.</p>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="resendEmailVerification" class="btn btn-sm btn-ghost" wire:loading.attr="disabled" wire:target="resendEmailVerification">
                                    <span wire:loading.remove wire:target="resendEmailVerification">Resend</span>
                                    <span wire:loading wire:target="resendEmailVerification">
                                        <span class="loading loading-spinner loading-xs"></span>
                                    </span>
                                </button>
                                <button wire:click="cancelEmailChange" class="btn btn-sm btn-ghost" wire:loading.attr="disabled" wire:target="cancelEmailChange">
                                    <span wire:loading.remove wire:target="cancelEmailChange">Cancel</span>
                                    <span wire:loading wire:target="cancelEmailChange">
                                        <span class="loading loading-spinner loading-xs"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endif
                </x-slot:passwordFields>
            </x-user-profile-fields>

            <!-- Submit Button -->
            <div class="form-control mt-8">
                <button type="submit" class="btn btn-primary btn-sm w-full" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>

        <!-- Export Data Button -->
        <div class="divider my-6"></div>
        <div class="text-center">
            <a href="{{ route('export.user-data') }}" class="btn btn-outline btn-sm gap-2" rel="noopener">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export My Data
            </a>
        </div>
    </div>
</div>
