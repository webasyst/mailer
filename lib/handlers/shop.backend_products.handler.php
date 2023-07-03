<?php

class mailerShopBackend_productsHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $user_rights = wa()->getUser()->getRights('mailer');
        $user_rights += [
            'backend' => 0,
            'author'  => 0
        ];
        $ui_version = wa()->whichUI('mailer');

        if (
            $ui_version === '2.0'
            && (wa()->getUser()->isAdmin() || $user_rights['backend'] >= 2 || $user_rights['author'] >= 1)
        ) {
            $backend_url = wa()->getConfig()->getBackendUrl(true);
            $lang = [
                'new'      => _wd('mailer', 'Create a new email campaign'),
                'create'   => _wd('mailer', 'Email campaign'),
                'template' => _wd('mailer', 'Select a template'),
                'no_name'  => _wd('mailer', 'No name'),
                'empty'    => sprintf(
                    _wd('mailer', 'You have no email templates that support embedding selected products data.<br><br>Create such a template in the %sMailer%s app.'),
                    "<a href=\"{$backend_url}mailer/#/templates/\">",
                    '</a>'
                )
            ];

            $letter_url = $backend_url.'mailer/#/campaigns/letter/?&template_id=';
            $icon = wa()->getAppStaticUrl('mailer', false).'img/mailer.svg';
            $preview_url = $backend_url.'mailer/?module=templates&action=Preview';
            $html = '
<style>
.templates-tiles {
    display:   flex;
    flex-wrap: wrap;
    gap:       1.5rem;
}
.templates-tiles li {
    cursor: pointer;
    margin: 0 !important;
    width: 182px !important;
}
.templates-tiles li .wrapper {
    position: relative;
}
.templates-tiles li:hover {
    overflow: visible;
}
.templates-tiles li:hover .no-image {
    border:     1px solid #1a9afe;
    box-shadow: 0 0 0 3px #1a9afe;
}
.templates-tiles .preview {
    position:      relative;
    text-align:    center;
    min-height:    132px;
    line-height:   132px;
    border-radius: 4px;
}
.templates-tiles .preview img {
    margin:         0 auto;
    border:         2px solid #ddd;
    max-width:      213px;
    max-height:     128px;
    vertical-align: bottom;
}
.templates-tiles .description {
    padding: 0 5px;
}
.templates-tiles .no-image {
    margin:        0 auto;
    width:         auto;
    height:        180px;
    border-radius: .375em;
    border:        1px solid rgba(0,0,0,0.1);
    overflow:      hidden;
    padding:       0;
    position:      relative;
}
.templates-tiles .no-image .catch-clicks {
    position: absolute;
    z-index:  10;
    left:     0;
    top:      0;
    right:    0;
    bottom:   0;
}
.templates-tiles .no-image iframe {
    width:                    400%;
    height:                   400%;
    overflow:                 hidden;
    overflow-y:               hidden;
    zoom:                     0.25;
    -moz-transform:           scale(0.25);
    -moz-transform-origin:    0 0;
    -o-transform:             scale(0.25);
    -o-transform-origin:      0 0;
    -webkit-transform:        scale(0.25);
    -webkit-transform-origin: 0 0;
    border:                   0 none;
}
.close-cross {
    position: absolute;
    top: 0.75rem;
    right: 1rem;
    font-size: 1.5rem;
    padding: 1rem;
    cursor: pointer;
}
.dialog-body {
    overflow-x: hidden;
    overflow-y: auto;
    height: 330px;
    padding-left: 5px;
}
.preview_h6 {
    margin-top: 0.75rem !important;
}
</style>
<li data-action="send_mailer" data-app="mailer" title="'.$lang['new'].'">
    <a href="#"><i class="icon16" style="background: url('.$icon.')"></i>'.$lang['create'].'</a>
</li>';

$html .= <<<HTML
<script>
    function escapeHtml(html) {
        return html
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    let mailer_dialog = function (html, product_ids) {
        $(html).waDialog({
            width: '750px',
            height: '350px',
            title: '<span class="close-cross"><i class="icon16 cross cancel"></i></span>{$lang['template']}',
            onLoad: function () {
                let that = this;
                $('i.cancel').on('click', function () {
                    $(that).trigger('close');
                });
                $.ajax({
                    url : '{$preview_url}',
                    type: 'POST',
                    data: { product_ids: product_ids },
                    dataType: 'json',
                    success: function (response) {
                        $(that).find('.dialog-body').html('');
                        if (response.errors) {
                            console.warn(response.errors);
                        } else if (0 === response.data.length) {
                            $(that).find('.dialog-body').append('<div class="align-center"><br><br><span>'+'{$lang['empty']}'+'</span></div>');
                        } else {
                            let tm;
                            $(that).find('.dialog-body').append('<br><ul id="template-list" class="templates-tiles thumbs li250px"></ul>');
                            for (let tmpl in response.data) {
                                tm = response.data[tmpl];
                                $(that).find('.dialog-body ul').append('\
                                    <li rel="'+ tm.id +'">\
                                        <div class="wrapper">\
                                            <div class="preview">\
                                                <div class="hidden iframe-preview-content">'+ escapeHtml(tm.body) +'</div>\
                                                <div class="no-image">\
                                                    <iframe scrolling="no"></iframe>\
                                                    <div class="catch-clicks"></div>\
                                                </div>\
                                            </div>\
                                            <h6 class="preview_h6">'+ (tm.subject || '{$lang['no_name']}') +'</h6>\
                                        </div>\
                                    </li>\
                                ');
                            }
                            $(that).find('.dialog-body').append('\
                                <script>\
                                    (function () {\
                                        "use strict";\
                                        const template_list = $(\'#template-list\');\
                                        const loadIframeContent = (iframe, content) => {\
                                            iframe = (iframe.contentWindow) ? iframe.contentWindow : (iframe.contentDocument.document) ? iframe.contentDocument.document : iframe.contentDocument;\
                                            iframe.document.open();\
                                            iframe.document.write(content);\
                                            iframe.document.close();\
                                        };\
                                        template_list.find(\'.iframe-preview-content\').each(function () {\
                                            const content = $(this);\
                                            const iframe = content.parent().find(\'iframe\');\
                                            loadIframeContent(iframe[0], content.text());\
                                        });\
                                    })();\
                                <\/script>\
                            ');
                        }
                        $('.templates-tiles').on('click', 'li', function () {
                            window.location.href = '{$letter_url}'+ $(this).attr('rel') +'&products='+ product_ids.join(',');
                        });
                    }
                });
            }
        });
    };

    $('li[data-action="send_mailer"]').on('click', 'a', function (e) {
        e.preventDefault();
        let tr;
        let product_ids = [];
        let product_checked = $('#product-list input:checked');
        if (product_checked.length === 0) {
            return;
        }

        for (let i = 0; i < product_checked.length; i++) {
            tr = product_checked[i].closest('tr,li');
            product_ids.push($(tr).data('product-id'));
        }

        mailer_dialog('\
            <div id="mailer_dialog">\
                <div class="dialog-content">\
                    <div class="dialog-body">\
                        <div class="align-center"><br>\
                            <i class="icon16 loading"></i>\
                        </div>\
                    </div>\
                </div>\
            </div>\
        ', product_ids);
    });
</script>
HTML;
            return array(
                'toolbar_export_li' => $html,
            );
        }

        return null;
    }
}
