<?php
/*
 * Controller to add or edit subscription form
 */
class mailerSubscribersFormeditorAction extends waViewAction
{
    public function execute()
    {
        if (!mailerHelper::isAdmin()) {
            throw new waException('Access denied.', 403);
        }

        $form_id = waRequest::get('id',0,'int');

        $msl = new mailerSubscribeListModel();
        $all_lists_list = $msl->getAllListsList();

        $this->view->assign('all_lists_list', $all_lists_list);

        if ($form_id > 0) {
            $mf = new mailerFormModel();
            $form = $mf->getById($form_id);

            $this->view->assign('uniqid', 'mailer'.md5(serialize($form)));

            $mfsl = new mailerFormSubscribeListsModel();
            $form['lists'] = $mfsl->getListsIds($form_id);

            $mfp = new mailerFormParamsModel();
            $form['params'] = $mfp->get($form_id);
        }
        else{
            $form = array();
        }

        $captcha_html = $this->renderCaptcha();

        $this->view->assign(array(
            'form' => $form,
            'captcha_html' => $captcha_html,
            'confirmation_template_vars' => $this->getConfirmationVars()
        ));
    }

    protected function renderCaptcha()
    {
        $img_url = 'img/waCaptchaImg.png';
        $isReCaptcha = waCaptcha::getCaptchaType('mailer') == 'waReCaptcha';
        if ($isReCaptcha) {
            $img_url = 'img/reCaptchaEN.png';
            if (wa()->getLocale() === 'ru_RU') {
                $img_url = 'img/reCaptchaRU.png';
            }
        }
        $view = wa()->getView();
        $old_vars = $view->getVars();
        $view->clearAllAssign();
        $view->assign(array(
            'img_url' => $img_url,
            'isReCaptcha' => $isReCaptcha
        ));
        $html = $view->fetch(wa()->getAppPath('templates/actions/subscribers/form_captcha.html', 'mailer'));
        $view->assign($old_vars);
        return $html;
    }

    protected function getConfirmationVars()
    {
        return array(
            '{SUBSCRIBER_NAME}' => _w("Subscriber name, if used in the form."),
            '{SUBSCRIPTION_CONFIRM_URL}' => _w("URL to confirm subscription. A click on this link opens “My subscriptions” page in the customer account.")
        );
    }
}
