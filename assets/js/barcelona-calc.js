/* barcelona-calc.js — loaded only when [barcelona_calc] shortcode is present */
/* global bcData */

(function () {
  'use strict';

  /* ------------------------------------------------------------------
   * Age bracket helpers for Palau de la Música family-ticket logic
   * ------------------------------------------------------------------ */
  var ADULT_AGES   = ['30-99','29','28','27','26','25','24','23','22','21','20','19','18']; // 18+
  var CHILD_AGES   = ['17','16','15','14','13','12','11','10'];                             // 10–17 (paying)
  // ages '9','8','7','0-6' are free (price = 0) so family ticket doesn't help them

  /* ------------------------------------------------------------------
   * Format a price number as "124,50 €"
   * ------------------------------------------------------------------ */
  function formatPrice(n) {
    if (n === 0) return '0 €';
    return n.toLocaleString('fr-FR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    }) + '\u00a0€';
  }

  /* ------------------------------------------------------------------
   * Return the currently selected ages (array of strings, incl. '–')
   * ------------------------------------------------------------------ */
  function getSelectedAges() {
    var selects = document.querySelectorAll('.bc-age-select');
    var ages = [];
    selects.forEach(function (sel) {
      ages.push(sel.value);
    });
    return ages;
  }

  /* ------------------------------------------------------------------
   * Compute the cost for one venue given the selected ages.
   * Handles family-ticket optimisation for Palau de la Música.
   * ------------------------------------------------------------------ */
  function calcVenueCost(venue, visitType, ages) {
    if (visitType === '–') return null; // not visiting

    var priceMatrix = venue.prices[visitType];
    if (!priceMatrix) return null;

    /* --- standard calculation --- */
    var standardTotal = 0;
    ages.forEach(function (age) {
      if (age === '–') return;
      standardTotal += (priceMatrix[String(age)] || 0);
    });

    /* --- family-ticket optimisation (Palau de la Música) --- */
    if (venue.familyTicket && venue.familyTicket[visitType]) {
      var ticketPrice = venue.familyTicket[visitType];

      var adultsCount   = 0;
      var childrenCount = 0;
      var freeCount     = 0;
      ages.forEach(function (age) {
        if (age === '–') return;
        var a = String(age);
        if (ADULT_AGES.indexOf(a) !== -1)  { adultsCount++;   }
        else if (CHILD_AGES.indexOf(a) !== -1) { childrenCount++; }
        else                               { freeCount++;     } // under 10, price already 0
      });

      if (adultsCount >= 2 && childrenCount >= 2) {
        var nTickets       = Math.min(Math.floor(adultsCount / 2), Math.floor(childrenCount / 2));
        var remAdults      = adultsCount   - nTickets * 2;
        var remChildren    = childrenCount - nTickets * 2;

        // remaining adults pay full adult rate; remaining children pay child rate
        // (at Palau, both adult and child price are the same, but kept generic)
        var adultRate  = priceMatrix['30-99'] || 0;
        var childRate  = priceMatrix['17']    || 0;

        var familyTotal = nTickets * ticketPrice
                        + remAdults   * adultRate
                        + remChildren * childRate;

        if (familyTotal < standardTotal) {
          return { cost: familyTotal, familyTickets: nTickets };
        }
      }
    }

    return { cost: standardTotal, familyTickets: 0 };
  }

  /* ------------------------------------------------------------------
   * Main recalculate — runs on every select change
   * ------------------------------------------------------------------ */
  function recalculate() {
    var ages    = getSelectedAges();
    var venues  = bcData.venues || [];
    var grandTotal = 0;
    var anySelected = false;

    venues.forEach(function (venue) {
      var selectEl   = document.querySelector('.bc-visit-select[data-venue="' + venue.id + '"]');
      var priceEl    = document.querySelector('.bc-price-output[data-venue="' + venue.id + '"]');
      var rowEl      = document.querySelector('.bc-venue-row[data-venue="' + venue.id + '"]');

      if (!selectEl || !priceEl) return;

      var visitType = selectEl.value;
      var result    = calcVenueCost(venue, visitType, ages);

      if (result === null) {
        // not visiting
        priceEl.textContent = '–';
        priceEl.classList.remove('bc-price-active', 'bc-price-family');
        if (rowEl) rowEl.classList.add('bc-row-skipped');
      } else {
        priceEl.textContent = formatPrice(result.cost);
        priceEl.classList.add('bc-price-active');
        priceEl.classList.remove('bc-price-family');
        if (rowEl) rowEl.classList.remove('bc-row-skipped');

        // Show family-ticket badge if applicable
        var familyBadge = rowEl && rowEl.querySelector('.bc-family-badge');
        if (result.familyTickets > 0) {
          priceEl.classList.add('bc-price-family');
          if (!familyBadge && rowEl) {
            var badge = document.createElement('span');
            badge.className = 'bc-family-badge';
            badge.textContent = bcData.texts && bcData.texts.family_ticket_note
              ? bcData.texts.family_ticket_note
              : 'billet famille appliqué';
            priceEl.parentNode.insertBefore(badge, priceEl.nextSibling);
          }
        } else {
          if (familyBadge) familyBadge.remove();
        }

        grandTotal += result.cost;
        anySelected = true;
      }
    });

    var totalEl = document.querySelector('.bc-total-output');
    if (totalEl) {
      totalEl.textContent = anySelected ? formatPrice(grandTotal) : '–';
    }
  }

  /* ------------------------------------------------------------------
   * Tooltip: toggle on button click (mobile-friendly);
   * close when clicking outside or pressing Escape.
   * ------------------------------------------------------------------ */
  function initTooltips() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.bc-tooltip-btn');
      var allBoxes = document.querySelectorAll('.bc-tooltip-box');

      if (btn) {
        e.stopPropagation();
        var box = btn.nextElementSibling; // .bc-tooltip-box
        var isOpen = box.classList.contains('bc-tooltip-open');

        // Close all others first
        allBoxes.forEach(function (b) { b.classList.remove('bc-tooltip-open'); });

        if (!isOpen) {
          box.classList.add('bc-tooltip-open');
          positionTooltip(btn, box);
        }
      } else if (!e.target.closest('.bc-tooltip-box')) {
        allBoxes.forEach(function (b) { b.classList.remove('bc-tooltip-open'); });
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.bc-tooltip-box').forEach(function (b) {
          b.classList.remove('bc-tooltip-open');
        });
      }
    });
  }

  /* Keep tooltip inside viewport */
  function positionTooltip(btn, box) {
    box.style.left = '';
    box.style.right = '';
    var rect    = box.getBoundingClientRect();
    var vw      = window.innerWidth || document.documentElement.clientWidth;
    if (rect.right > vw - 8) {
      box.style.right = '0';
      box.style.left  = 'auto';
    }
  }

  /* ------------------------------------------------------------------
   * Bootstrap
   * ------------------------------------------------------------------ */
  function init() {
    if (!window.bcData) return;

    // Attach change listeners
    document.querySelectorAll('.bc-age-select, .bc-visit-select').forEach(function (sel) {
      sel.addEventListener('change', recalculate);
    });

    initTooltips();

    // Run once on load to reflect default selections
    recalculate();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
