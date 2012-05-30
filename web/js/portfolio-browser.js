(function($) {
  "use strict";

  $.fn.portfolioBrowser = function(currentId) {
    var dot_nav = this.find('.dot-nav');
    var container = this.find('.elementcontainer');
    var current = container.find('.element');
    var currentIdx;

    /* Multiple elements in the container need to overlap during the transition.
     * Therefore elements need to be absolutely positioned in a relative positioned container.
     * Absolute positioning however destroys the native CSS centering using display:table-cell and vertical-align:middle
     * therefore we transform the elements into positioning with negative margins
     */
    function absoluteCenter(el) {
      var child = el.children().first();
      el.css({
        'position': 'absolute',
        'top': '50%',
        'left': '50%',
        'margin-left': -1 * (child.width() / 2),
        'margin-top': -1 * (child.height() / 2)
      });
    }

    container.css({'position': 'relative'});
    absoluteCenter(current);

    /* List of elements */
    var elements;

    function findElementIndex(id) {
      for (var idx = 0; idx < elements.length; idx++)
          if (elements[idx].id == id)
            return idx;

      // TODO not found: return a fake error element?
      alert('not-found');
    }

    /* Get the staged (pre-loaded) element contents. If it isn't, stage it now
     * by adding the HTML to the container */
    function getStagedElement(element) {
      if (typeof element.staged === "undefined") {
        var stagedHtml = $(element.html);
        stagedHtml.css({'display': 'none'});
        container.append(stagedHtml);
        absoluteCenter(stagedHtml);
        element.staged = stagedHtml;
      }

      return element.staged;
    }


    /* Transition the current element to element with id "id" and update meta-data and navigation */
    function showElement(id) {
      /* Note: id isn't necessarily an element code. Element codes are only
       * guaranteed to be unique in a group. It's a string uniquely identifying an element in a browsing set.
       */
      var idx = findElementIndex(id);
      if (idx == currentIdx)
        return; /* Nothing to do, this element is already shown. Don't animate */

      var element = elements[idx];

      var staged = getStagedElement(element);

      /* Cross-fade the new element in (and hide the old afterwards) */
      staged.css({'display': 'block', 'opacity': 0});
      staged.animate({'opacity': 1});
      current.animate({'opacity': 0}, function () {
        current.css({'display': 'none'});

        /* The staged element should now be considered current as the old one is hidden */
        current = staged;
        currentIdx = idx;

        preloadNextPrev();
      });

      // TODO update meta-data
      // TODO update navigation

      /* Change the URL (ignore older browsers) */
      if (Modernizr.history)
        window.history.pushState(null, null, id);
    }

    /* Preload the previous and next elements by already requesting the element on stage */
    function preloadNextPrev() {
      if ((currentIdx + 1) < elements.length) {
        getStagedElement(elements[currentIdx + 1]);
      }
      if ((currentIdx - 1) >= 0) {
        getStagedElement(elements[currentIdx - 1]);
      }
    }

    /* Make existing navigation elements in the page use this portfolio browser for switching */
    function attachToNavigation() {
      dot_nav.find('a').click(function () {
        showElement($(this).attr('href'));
        return false;
      });
    }

    /* Retrive the data for the current browsing set */
    $.getJSON('browser-data', function(data) {
      elements = data;
      attachToNavigation();

      currentIdx = findElementIndex(currentId);
      preloadNextPrev();
    });
  };

})(jQuery);
