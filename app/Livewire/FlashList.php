<?php

namespace App\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class FlashList extends Component
{
    use WithPagination;

    public $editingFlashId = null;

    public $deletingFlashId = null;

    #[On('flash-saved')]
    #[On('flash-deleted')]
    public function refresh()
    {
        // Reset pagination to page 1 when new flash is added
        $this->resetPage();
    }

    public function openEditModal($flashId)
    {
        $this->editingFlashId = $flashId;
    }

    #[On('close-edit-modal')]
    public function closeEditModal()
    {
        $this->editingFlashId = null;
    }

    public function confirmDelete($flashId)
    {
        $this->deletingFlashId = $flashId;
    }

    public function cancelDelete()
    {
        $this->deletingFlashId = null;
    }

    public function delete()
    {
        if (! $this->deletingFlashId) {
            return;
        }

        $flash = \App\Models\Flash::findOrFail($this->deletingFlashId);

        // Authorization check
        if (auth()->id() !== $flash->user_id) {
            abort(403);
        }

        // Check if flash is within editable date range
        $now = now();
        $minDate = $this->getMinAllowedDate($now);
        $maxDate = $now->copy()->addDay();

        if (! $flash->isEditable($minDate, $maxDate)) {
            abort(403, 'This activity is outside the editable date range.');
        }

        $flash->delete();

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Flash deleted!',
        ]);

        // Dispatch event to refresh other components
        $this->dispatch('flash-deleted');

        // Close the delete modal
        $this->deletingFlashId = null;
    }

    public function render()
    {
        $user = auth()->user();
        $flashes = $user->flashes()
            ->orderBy('date', 'desc')
            ->paginate(15);

        // Calculate min/max dates for edit/delete authorization
        $now = now();
        $minDate = $this->getMinAllowedDate($now);
        $maxDate = $now->copy()->addDay();

        return view('livewire.flash-list', [
            'flashes' => $flashes,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
        ]);
    }

    /**
     * Calculate the minimum allowed date based on grace period logic.
     * January allows previous year entries, February onward restricts to current year.
     */
    private function getMinAllowedDate(Carbon $now): Carbon
    {
        $minDate = $now->copy()->startOfYear();
        if ($now->month === 1) {
            // January: allow previous year entries (grace period)
            $minDate = $now->copy()->subYear()->startOfYear();
        }

        return $minDate;
    }
}
