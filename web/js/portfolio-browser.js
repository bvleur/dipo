(function($) {
  "use strict";

  $.fn.portfolioBrowser = function(initialContainerCode, initialId) {
    var dotNav = this.find('.dot-nav ul');
    var portfolioNav = $('#menu, #tagcloud');
    var viewContainer = this.find('.elementcontainer');
    var description = this.find('figcaption');
    var tagcontainer = this.find('.elementtags');
    var taglist = tagcontainer.find('ul');
    var current = viewContainer.find('.element');

    var currentElement;

    /** MODEL **/

    function Container(code, data) {
      this.code = code;
      this.description = data.description;
      this.elements = [];
      for (var i = 0; i < data.elements.length; i++) {
        this.elements.push(new Element(this, i, data.elements[i]));
        if (i > 0) {
          this.elements[i - 1].next = this.elements[i];
          this.elements[i].previous = this.elements[i - 1];
        }
      }
    }

    Container.prototype.getElement = function (id) {
      for (var i = 0; i < this.elements.length; i++)
          if (this.elements[i].id == id)
            return this.elements[i];
    }

    function Element(container, idx, data) {
      this.container = container;
      this.idx = idx;
      this.id = data.id;
      this.description = data.description;
      this.tags = data.tags;
      this.html = data.html;
    }


    function ContainerList() {
      this.containers = [];
      return this;
    }

    ContainerList.prototype.withContainer = function (code, action) {
      if ((code in this.containers)) {
        action(this.containers[code]);
        return;
      }

      var list = this;
      $.getJSON(generateURL(code, 'browser-data'), function(data) {
        list.containers[code] = new Container(code, data);
        action(list.containers[code]);
      });
    }

    var containers = new ContainerList();

    /** VIEW **/

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

    viewContainer.css({'position': 'relative'});
    absoluteCenter(current);

    function generateURL(containerCode, id) {
      return '/portfolio/' + encodeURIComponent(containerCode) + '/' +  encodeURIComponent(id);
    }

    /* Get the staged (pre-loaded) element contents. If it isn't, stage it now
     * by adding the HTML to the container */
    var stage = [];
    function getStaged(element) {
      if (!stage[element.container.code])
        stage[element.container.code] = [];

      if (!stage[element.container.code][element.id]) {
        var staged = $(element.html)
          .css({'display': 'none'})
          .appendTo(viewContainer);

        absoluteCenter(staged);

        stage[element.container.code][element.id] = staged;
      }
      return stage[element.container.code][element.id];
    }

    /* Transition the current element to element with id "id" and update meta-data and navigation */
    function showElement(element, noPushState) {
      var staged = getStaged(element);

      /* Do not switch if the element to be shown is the same as the current */
      if (current.is(staged))
        return;

      /* Cross-fade the new element in (and hide the old afterwards) */
      staged.css({'display': 'block', 'opacity': 0});
      staged.animate({'opacity': 1});

      current.animate({'opacity': 0}, function () {
        $(this).css({'display': 'none'});
        preloadNextPrev();
      });

      /* Update description */
      description.html(element.description || element.container.description);

      /* Update tags */
      updateTags(element.tags)

      /* Update navigation */
      updateDotNav(element);

      /* Change the URL (ignore older browsers) */
      if (Modernizr.history && !noPushState) {
        window.history.pushState({containerCode: element.container.code, id: element.id}, null,  generateURL(element.container.code, element.id));
      }

      /* This element should now be considered current */
      current = staged;
      currentElement = element;
    }

    function updateDotNav(element) {
      if (element.container === currentElement.container) {
        /* dotNav is already up to date, except that the old "current" is still marked */
        dotNav.find('.current').removeClass('current');
      } else {
        /* recreate the dotNav for the current container */
        var elements = element.container.elements;
        dotNav.html('');
        for (var i = 0; i < elements.length; i++) {
          var dot = $('<li><a href="' + generateURL(elements[i].container.code, elements[i].id) + '">o</a></li>');
          dot.data('element', elements[i]);
          dotNav.append(dot);
        }
      }

      /* Mark current */
      dotNav.find('li').eq(element.idx).addClass('current');
    }

    function updateTags(elementTags) {
      /* Determine mutations (movements, removals, additions) */
      var elementTagNames = $.map(elementTags, function (a) { return a.name + ""; });
      var notNew = [];
      var toRemove = [];
      var toMove = [];

      taglist.find('li').each(function(currentIndex) {
        var newIndex = $.inArray($(this).text(), elementTagNames);
        if (newIndex === -1) {
          toRemove.push(this)
        } else {
          notNew.push(newIndex);
          if (newIndex != currentIndex) {
            toMove.push({tag: this, from: currentIndex, to: newIndex});
          }
        }
      });

      /* Effectuate mutations */
      $.each(toRemove, function (index, tag) { $(tag).remove(); });
      $.each(elementTags, function (index, elementTag) {
        if ($.inArray(index, notNew) === -1) {
          var newTag = '<li><a href="' + generateURL(elementTag.code, elementTag.firstElementId) + '">' + elementTag.name + '</a></li>';
          if (index == 0) {
            taglist.prepend(newTag);
          } else {
            taglist.find('li').eq(index - 1).after(newTag);
          }
        }
      });

      $.each(toMove, function (index, movement) {
        if (taglist.find('li').eq(movement.to).is(movement.tag)) {
          return;
        }
        if (movement.to == 0) {
          taglist.find('li').eq(0).before($(movement.tag));
        } else {
          if (movement.from < movement.to) {
            taglist.find('li').eq(movement.to).after($(movement.tag));
          } else {
            taglist.find('li').eq(movement.to - 1).after($(movement.tag));
          }
        }
      });

      tagcontainer.toggle((elementTags.length !== 0));
    }

    /* Preload the previous and next elements by already requesting the element on stage */
    function preloadNextPrev() {
      if (currentElement.next) {
        getStaged(currentElement.next);
      }
      if (currentElement.previous) {
        getStaged(currentElement.previous);
      }
    }

    function showByCodeAndId(containerCode, elementId, noPushState) {
      containers.withContainer(containerCode, function(container) {
        showElement(container.getElement(elementId), noPushState);
      });
    }

    /* Make existing navigation elements in the page use this portfolio browser for switching */
    function attachToNavigation() {
      dotNav.find('a').each(function () {
        var id = decodeURIComponent($(this).attr('href'));
        $(this).parent().data('element', currentElement.container.getElement(id));
      });

      dotNav.on('click', 'a', function () {
        showElement($(this).parent().data('element'));
        return false;
      });

      portfolioNav.find('a').click(function (e) {
        portfolioNav.find('#current-page').removeAttr('id');
        $(this).parent().attr('id', 'current-page');

        var uri = decodeURIComponent($(this).attr('href'));
        var parts = uri.match(/\/portfolio\/(.*)\/(.*)/);
        showByCodeAndId(parts[1], parts[2]);
        return false;
      });

      if (Modernizr.history) {
        window.onpopstate = function (event) {
          showByCodeAndId(event.state.containerCode, event.state.id, true);
        }
      }
    };

    /* Retrive the data for the current browsing set */
    containers.withContainer(initialContainerCode, function (container) {
      currentElement = container.getElement(initialId);
      stage[currentElement.container.code] = {};
      stage[currentElement.container.code][currentElement.id] = current;

      window.history.replaceState({containerCode: initialContainerCode, id: initialId}, null);

      attachToNavigation();
      preloadNextPrev();
    });

  }; // fn.portfolioBrowser

})(jQuery);
