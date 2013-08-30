(function($) {
  "use strict";

  $.fn.portfolioBrowser = function(initialContainerCode, initialId) {
    var thiz = $(this);
    var dotNav = this.find('.dot-nav ul');
    var portfolioNav = $('#menu, #tagcloud');
    var viewContainer = this.find('.elementcontainer');
    var description = this.find('figcaption');
    var tagcontainer = this.find('.elementtags');
    var taglist = tagcontainer.find('ul');
    var current = viewContainer.find('.element');
    var previous = this.find('.previous');
    var next = this.find('.next');

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
      this.containerSizeCode = data.containerSizeCode;
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

    description.css('position', 'relative');

    function generateURL(containerCode, id) {
      if (arguments[0] instanceof Element) {
        containerCode = arguments[0].container.code;
        id = arguments[0].id
      }

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

      /* Update container size class */
      if (currentElement.containerSizeCode != element.containerSizeCode) {
        viewContainer
          .removeClass('element-container-size-' + currentElement.containerSizeCode)
          .addClass('element-container-size-' + element.containerSizeCode)
          ;
      }

      /* Cross-fade the new element in (and hide the old afterwards) */
      staged.fadeIn();
      current.fadeOut(function () {
        preloadNextPrev();
      });

      /* Update description */
      var newElementDescription = element.description || element.container.description;
      if (description.html() !== newElementDescription) {
        var oldDescription = description
          .clone()
          .css({
            position: 'absolute',
            top: description.position().top,
            left: description.position().left
          })
          .appendTo(description.parent())

        description.html(newElementDescription);
        oldDescription.fadeOut('fast', function () { $(this).remove(); });
      }

      /* Update tags */
      updateTags(element.tags)

      /* Update navigation */
      updateNav(element);

      /* Change the URL (ignore older browsers) */
      if (Modernizr.history && !noPushState) {
        window.history.pushState({containerCode: element.container.code, id: element.id}, null,  generateURL(element));
      }

      /* This element should now be considered current */
      current = staged;
      currentElement = element;

      thiz.trigger('elementchanged');
    }

    function updatePageNav(element) {
      if (element.container !== currentElement.container) {
        portfolioNav.find('#current-page').removeAttr('id');
        portfolioNav.find('a[href*="/' + element.container.code + '/"]').parent().attr('id', 'current-page');
      }
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
          var dot = $('<li><a href="' + generateURL(elements[i]) + '">o</a></li>');
          dot.find('a').data('element', elements[i]);
          dotNav.append(dot);
        }
      }

      /* Mark current */
      dotNav.find('li').eq(element.idx).addClass('current');
    }

    function updateNav(element) {
      if (element.previous) {
        previous.data('element', element.previous);
        previous.attr('href', generateURL(element.previous));
      } else {
        previous.removeData('element');
      }
      if (element.next) {
        next.data('element', element.next);
        next.attr('href', generateURL(element.next));
      } else {
        next.removeData('element');
      }
      updatePageNav(element);
      updateDotNav(element);
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
      /* Initialize the "element" data value on the items in the dot-nav and arrows */
      dotNav.find('a').add(previous).add(next).each(function () {
        var id = decodeURIComponent($(this).attr('href'));
        if (id != '#') {
          $(this).data('element', currentElement.container.getElement(id));
        }
      });

      /* Hook up the links in the dot-nav to switch the element */
      dotNav.on('click', 'a', function () {
        showElement($(this).data('element'));
        return false;
      });

      previous.add(next).click(function () {
        if ($(this).data('element')) {
          showElement($(this).data('element'));
        }
        return false;
      });

      previous.add(next).hover(function () {
        $(this).animate({opacity: 0.7});
      }, function () {
        $(this).animate({opacity: 0});
      }
      ).css({opacity: 0});


      /* Keyboard left and right arrows navigate previous and next
       * and up and down navigate through sets
       * */
      $(document).keydown(function(e) {
        if (e.keyCode == 37 && currentElement.previous) {
            showElement(currentElement.previous);
            return false;
        } else if (e.keyCode == 39 && currentElement.next) {
            showElement(currentElement.next);
            return false;
        }

        var relative;
        if (e.keyCode == 38) {
          relative = -1;
        } else if (e.keyCode == 40) {
          relative = 1;
        } else {
          return true;
        }
        var navSet = $('#current-page').parents('nav').find('li');
        var newIndex = navSet.index($('#current-page')) + relative;
        if (newIndex == navSet.length || newIndex == -1) {
          var curNavIndex = portfolioNav.index($('#current-page').parents('nav').first());
          portfolioNav.eq((curNavIndex + relative) % portfolioNav.length).find('a').eq(relative == -1 ? -1 : 0).click();
        } else {
          navSet.eq(newIndex).find('a').click();
        }

        return false;
      });

      /* Hook up the links in the global navigation to switch the element */
      portfolioNav.add(tagcontainer).on('click', 'a', function () {
        var uri = decodeURIComponent($(this).attr('href'));
        var parts = uri.match(/\/portfolio\/(.*)\/(.*)/);
        showByCodeAndId(parts[1], parts[2]);
        return false;
      });

      /* Hook up changing the history state to switch to the right element */
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

      if (Modernizr.history)
        window.history.replaceState({containerCode: initialContainerCode, id: initialId}, null);

      attachToNavigation();
      preloadNextPrev();
    });

    $('.fb-like-hover').hoverIntent(function() {
      $(this).animate({height: '20px', width: '130px'});
      $(this).find('img').fadeOut();
      $(this).prepend('<div class="fb-like" data-href="' + window.location.protocol + '//' + window.location.hostname + generateURL(currentElement) + '" ' +
        'data-send="false" data-layout="button_count" data-width="130" data-show-faces="false" data-font="arial"></div>');
      FB.XFBML.parse(this);
    }, function () {
      $(this).animate({height: '17px', width: '16px'});
      $(this).find('img').fadeIn();
      $(this).find('.fb-like').fadeOut(function () {$(this).remove();});
    }).show();;

  }; // fn.portfolioBrowser

})(jQuery);
