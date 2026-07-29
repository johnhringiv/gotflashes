/**
 * Chromium base-select touch quirk: a mouse click on the control of an OPEN
 * picker closes it, but a TAP leaves it open. Instrumented sequence of the
 * second tap: pointerdown (open) → pointerup light-dismisses (closed) →
 * synthesized mousedown sees a CLOSED select and re-opens it. Net effect:
 * the picker looks stuck open on mobile.
 *
 * Fix: when a TOUCH pointerdown lands on a select whose picker is open,
 * cancel the synthesized mousedown so the light dismiss stands. Touch only —
 * for a real mouse the mousedown's default action IS the toggle-close, and
 * preventing it would break desktop click-to-close.
 */
document.addEventListener('pointerdown', (e) => {
    const select = e.target.closest('select');
    if (select) {
        select._openTouchTap = e.pointerType === 'touch'
            && select.matches(':open')
            // Taps INSIDE the picker (options are DOM children of the
            // select) must keep their default action: selecting the option
            && !e.target.closest('option, optgroup');
    }
}, true);

document.addEventListener('mousedown', (e) => {
    const select = e.target.closest('select');
    if (select && select._openTouchTap) {
        select._openTouchTap = false;
        e.preventDefault();
    }
}, true);
