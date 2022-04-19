if (!RedactorPlugins) var RedactorPlugins = {};

RedactorPlugins.vars = function () {
    return {
        init: function () {
            var button = this.button.add('clips', $_('Insert variable'));
            this.button.setIcon(button, '<i class="re-icon-clips"></i>');
            this.button.addCallback(button, this.vars.show);
        },
        getTemplate: function () {
            var $template = $('<div class="modal-section" id="redactor-modal-vars">'
                + '<section id="redactor-vars-list">'
                + '</section>'
                + '<footer>'
                + '<button id="redactor-modal-button-cancel">' + $_('Close') + '</button>'
                + '</footer>'
                + '</div>');
            $template.find('#redactor-vars-list').html($('#available-smarty-variables').html());

            return $template;
        },
        show: function () {
            this.modal.addTemplate('vars', this.vars.getTemplate());
            this.modal.load('vars', $_('Insert variable'), 700);

            $('#redactor-modal-vars').find('.one-var').each($.proxy(function (i, s) {
                $(s).click($.proxy(function () {
                    this.vars.insertClip($(s).find('.var-code').text());
                    return false;
                }, this));
            }, this));

            this.modal.show();
        },
        insertClip: function (html) {
            this.insert.html($.trim(html));
            this.modal.close();
        }
    };
};