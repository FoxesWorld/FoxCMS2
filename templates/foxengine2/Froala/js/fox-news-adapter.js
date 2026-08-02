(function (global) {
  'use strict';

  var root = '/templates/foxengine2/Froala';
  var loading = null;

  function stylesheet(id, href) {
    if (document.getElementById(id)) return;
    var link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  function script(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[src="' + src + '"]');
      if (existing && existing.dataset.loaded === 'true') return resolve();
      var element = existing || document.createElement('script');
      element.addEventListener('load', function () {
        element.dataset.loaded = 'true';
        resolve();
      }, { once: true });
      element.addEventListener('error', function () {
        reject(new Error('Не удалось загрузить ' + src));
      }, { once: true });
      if (!existing) {
        element.src = src;
        element.defer = true;
        document.head.appendChild(element);
      }
    });
  }

  function load() {
    if (global.FroalaEditor) return Promise.resolve(global.FroalaEditor);
    if (!loading) {
      stylesheet('fox-froala-core', root + '/css/froala_editor.pkgd.min.css');
      stylesheet('fox-froala-theme', root + '/css/editor.css');
      loading = script(root + '/js/froala_editor.pkgd.min.js')
        .then(function () { return script(root + '/languages/ru.js'); })
        .then(function () {
          if (!global.FroalaEditor) throw new Error('Froala Editor не инициализирован.');
          return global.FroalaEditor;
        });
    }
    return loading;
  }

  global.FoxNewsFroala = {
    mount: function (element, settings) {
      settings = settings || {};
      return load().then(function (FroalaEditor) {
        return new Promise(function (resolve) {
          var instance = new FroalaEditor(element, {
            language: 'ru',
            theme: 'gray',
            attribution: false,
            placeholderText: settings.placeholder || 'Начните писать текст новости…',
            heightMin: 360,
            heightMax: 760,
            toolbarSticky: true,
            toolbarStickyOffset: 72,
            charCounterCount: true,
            charCounterMax: settings.maximumLength || 100000,
            quickInsertEnabled: false,
            imagePaste: false,
            imageUpload: false,
            videoUpload: false,
            fileUpload: false,
            pasteDeniedTags: ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'],
            toolbarButtons: [
              'paragraphFormat', 'bold', 'italic', 'underline', 'strikeThrough',
              'formatOL', 'formatUL', 'align', 'insertLink', 'insertTable', 'quote',
              'insertHR', 'clearFormatting', 'undo', 'redo', 'html', 'fullscreen'
            ],
            toolbarButtonsXS: [
              'paragraphFormat', 'bold', 'italic', 'formatUL', 'formatOL',
              'insertLink', 'undo', 'redo', 'fullscreen'
            ]
          }, function () {
            var editor = this;
            editor.html.set(settings.value || '');
            if (settings.disabled) editor.edit.off();
            editor.events.on('contentChanged', function () {
              if (typeof settings.onChange === 'function') settings.onChange(editor.html.get(true));
            });
            resolve({
              setValue: function (value) {
                if (editor.html.get(true) !== value) editor.html.set(value || '');
              },
              setDisabled: function (disabled) {
                if (disabled) editor.edit.off(); else editor.edit.on();
              },
              destroy: function () { editor.destroy(); }
            });
          });
          void instance;
        });
      });
    }
  };
})(window);
