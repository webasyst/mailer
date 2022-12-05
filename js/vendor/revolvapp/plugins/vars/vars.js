/**
 * Webasyst LLC
 * Version 1.0.0
 *
 * https://www.webasyst.com/
 *
 * Copyright (c) 2022, Webasyst
 */

(function () {
    Revolvapp.add('plugin', 'vars', {
        translations: {
            en: {
                "vars": {
                    "variable": "Variables"
                }
            }
        },
        defaults: {
            icon: '<svg height="16" viewBox="0 0 16 16" width="16" xmlns="http://www.w3.org/2000/svg"><path d="m8 0c4.418278 0 8 3.581722 8 8s-3.581722 8-8 8-8-3.581722-8-8 3.581722-8 8-8zm0 2c-3.3137085 0-6 2.6862915-6 6s2.6862915 6 6 6 6-2.6862915 6-6-2.6862915-6-6-6zm-.04375592 2c1.59851929 0 2.78098562.82644628 2.84667822 2.45730028h-1.72990447c-.01094876-.52892562-.4051042-.90358127-1.11677375-.90358127-.67882327 0-1.02918366.30853994-1.02918366.71625344 0 1.36639119 4.07293958.12121212 4.07293958 3.23966942 0 1.43250693-1.08392747 2.49035813-3.01090964 2.49035813-1.86128959 0-2.99996087-1.0688705-2.98901211-2.65564738h1.65326311c.03284629.6060606.51459183 1.05785128 1.39049282 1.05785128.72261831 0 1.14962004-.2754821 1.14962004-.76033062 0-1.43250689-3.99629825-.15426997-3.99629825-3.32782369 0-1.2892562.99633737-2.31404959 2.75908811-2.31404959z"/></svg>',
            items: {},
            classname: Revolvapp.prefix +'-vars',
            template: {
                start: '',
                end: ''
            }
        },
        init: function () {},
        start: function () {
            this.app.toolbar.add('vars', {
                title: '## vars.variable ##',
                icon: this.opts.vars.icon,
                components: 'editable',
                command: 'vars.popup'
            });
        },
        popup: function (button, name, e) {
            let params = {
                width: '600px',
                builder: 'vars.buildItems',
            };

            this.app.popup.create('vars', params);
            this.app.popup.open({button: button});
        },
        insert: function (params, button, name) {
            this.app.popup.close();

            let start = this.opts.vars.template.start;
            let end = this.opts.vars.template.end;

            this.app.component.insert(start + name + end, 'after');
        },
        buildItems: function (stack) {
            let hint;
            let description;
            let that = this;
            let vars = this.opts.vars.items;
            let $fields = this.dom('<div>').addClass('fields');

            for (let variable in vars) {
                description = (vars[variable]['description'] ? variable : '');
                hint = (vars[variable]['description'] ? vars[variable]['description'] : '');
                $fields.append(this._group_items(vars[variable], description, hint));
            }

            $fields.find('.'+ this.opts.vars.classname).on('click.revolvapp', function (e) {
                e.preventDefault();
                e.stopPropagation();

                that.app.insertion.insertNode(this.dataset.name, 'after');
                that.app.popup.close();
                let instance = that.app.component.get();
                if (instance.isEditable()) {
                    instance.sync();
                    that.app.editor.adjustHeight();
                }
            });

            stack.$body.attr('style', 'margin: 15px');
            stack.$body.append($fields);
        },
        _group_items: function ($items, group_title, hint) {
            let $fields_group = this.dom('<div>').addClass('fields-group');
            if (group_title) {
                $fields_group.append(this.dom('<h5>').html(group_title));
                if (hint) {
                    $fields_group.append(this.dom('<p>').addClass('hint').html(hint));
                }
            }
            for (let variable in $items) {
                if (variable === 'description') {
                    continue;
                }
                $fields_group.append(this._wrap_item(variable, $items[variable]));
            }

            return $fields_group;
        },
        _wrap_item: function (_name, _value) {
            let $field;
            let reg = /(\S{1,25})/g;
            let reg_link = /^%\S+_LINK%$/;
            let chunks = _name.match(reg);
            let style = 'font-family: SFMono-Regular, Consolas, "Liberation Mono", Menlo, Courier, monospace;\
                background-color: #dae9ff;\
                display: inline-block;\
                border-radius: 3px;\
                font-size: 13px;\
                color: #111;\
                line-height: 1;\
                min-height: initial;\
                padding: 4px 10px;\
                white-space: nowrap;\
                text-transform: none;\
                font-weight: normal;';
            let $name = this.dom('<div>').addClass('name').attr('style', 'width: 250px');
            let $var = this.dom('<span>').attr('style', style);
            let $value = this.dom('<div>')
                .addClass('value')
                .attr('style', 'vertical-align: middle')
                .html('<span class="gray">'+ _value +'</span>');

            if (reg_link.test(_name)) {
                let st = 'font-family: Helvetica, Arial, sans-serif;\
                    font-size: 16px;\
                    font-weight: normal;\
                    line-height: 1.5;\
                    color: rgb(0, 145, 255);\
                    text-decoration: underline;';
                _name = '<a href="'+ _name +'" style="'+ st +'">'+ _value +'</a>';
            }
            $field = this.dom('<div>')
                .addClass('field rex-popup-item '+ this.opts.vars.classname)
                .attr('style', 'padding: 0')
                .attr('data-name', _name);

            $var.html(chunks.join('<br>'));
            $name.html($var);
            $field.append($name);
            $field.append($value);

            return $field;
        }
    });
})(Revolvapp);
