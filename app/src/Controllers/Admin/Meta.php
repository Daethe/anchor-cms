<?php

namespace Anchorcms\Controllers\Admin;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Anchorcms\Controllers\AbstractController;
use Anchorcms\Forms\Meta as MetaForm;

class Meta extends AbstractController
{
    protected $prefix = 'global_';

    public function getIndex(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $query = $this->container['mappers.meta']->query()
            ->where('key NOT LIKE :key')
            ->setParameter('key', $this->prefix.'%')
            ->orderBy('key', 'asc');
        $meta = $this->container['mappers.meta']->fetchAll($query);

        $form = new MetaForm([
            'method' => 'post',
            'action' => $this->container['url']->to('/admin/meta/update'),
        ]);
        $form->init();

        $form->getElement('_token')->setValue($this->container['csrf']->token());

        $options = $this->container['mappers.pages']->dropdownOptions();

        $form->getElement('home_page')->setOptions($options);

        $form->getElement('posts_page')->setOptions($options);

        $values = [];

        foreach ($meta as $row) {
            $values[$row->key] = $row->value;
        }

        $form->setValues($this->container['session']->getStash('input', $values));

        $vars['sitename'] = $this->container['mappers.meta']->key('sitename');
        $vars['title'] = 'Site Metadata';
        $vars['form'] = $form;

        $this->renderTemplate($response, 'layouts/default', 'meta/edit', $vars);

        return $response;
    }

    public function postUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $request->getAttribute('id');

        $form = new \Forms\Meta();
        $form->init();

        $input = filter_input_array(INPUT_POST, $form->getFilters());
        $validator = $this->container['validation']->create($input, $form->getRules());

        $validator->addRule(new \Forms\ValidateToken($this->container['csrf']->token()), '_token');

        if (false === $validator->isValid()) {
            $this->container['messages']->error($validator->getMessages());
            $this->container['session']->putStash('input', $input);

            return $this->redirect($this->container['url']->to('/admin/meta'));
        }

        unset($input['token']);

        foreach ($input as $key => $value) {
            $this->container['mappers.meta']->where('key', '=', $key)->update([
                'value' => $value,
            ]);
        }

        $this->container['messages']->success('Metadata updated');

        return $this->redirect($this->container['url']->to('/admin/meta'));
    }
}
