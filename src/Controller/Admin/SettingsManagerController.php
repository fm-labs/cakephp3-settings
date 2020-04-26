<?php
declare(strict_types=1);

namespace Settings\Controller\Admin;

use Cake\Cache\Cache;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\Utility\Hash;
use Settings\Form\SettingsForm;
use Settings\SettingsManager;

/**
 * Settings Controller
 *
 * @property \Settings\Model\Table\SettingsTable $Settings
 */
class SettingsManagerController extends AppController
{
    /**
     * @var string
     */
    public $modelClass = false;

    public $actions = [
        'edit' => 'Backend.Edit',
        'view' => 'Backend.View',
    ];

    /**
     * @var \Settings\SettingsManager
     */
    public $_settingsManager;

    /**
     * @return \Settings\SettingsManager
     */
    public function settingsManager()
    {
        if (!$this->_settingsManager) {
            $manager = new SettingsManager();
            $this->getEventManager()->dispatch(new Event('Settings.build', null, ['manager' => $manager]));
            $this->_settingsManager = $manager;
        }

        return $this->_settingsManager;
    }

    /**
     * Load settings values from persistent storage
     *
     * @param string $scope Settings scope
     * @return array
     */
    protected function _loadValues($scope)
    {
        $values = [];
        $settings = $this->Settings
            ->find()
            ->where(['Settings.scope' => $scope])
            ->all();

        foreach ($settings as $setting) {
            $values[$setting->key] = $setting->value;
        }

        return $values;
    }

    protected function _saveValues($scope, $compiled)
    {
        $values = $compiled;

        // update existing
        $settings = $this->Settings
            ->find()
            ->where(['Settings.scope' => $scope])
            ->all();

        //@TODO Use database transaction to save settings
        $this->Settings->getConnection()->begin();
        foreach ($settings as $setting) {
            $key = $setting->key;
            $value = $values[$key] ?? null;
            unset($values[$key]);
            if ($value == $setting->value) {
                continue;
            }
            $setting->set('value', $value);
            if (!$this->Settings->save($setting)) {
                Log::error("Failed saving setting for key $key", ['settings']);

                return false;
            }
        }

        // add new
        foreach ($this->settingsManager()->getSettings() as $key => $config) {
            if ($config['scope'] !== $scope || !array_key_exists($key, $values)) {
                continue;
            }
            $setting = $this->Settings->newEntity(['key' => $key, 'value' => $values[$key], 'scope' => $scope]);
            if (!$this->Settings->save($setting)) {
                Log::error("Failed adding setting for key $key", ['settings']);

                return false;
            }
        }
        $this->Settings->getConnection()->commit();

        Cache::clear('settings');
        Configure::write($compiled);

        return true;
    }

    public function manage($scope = 'global')
    {
        $values = $this->_loadValues($scope);
        $this->settingsManager()->apply($values);

        if ($this->request->is(['post', 'put'])) {
            $values = Hash::flatten($this->request->getData());
            $this->settingsManager()->apply($values);
            $compiled = $this->_settingsManager->getCompiled();
            if (!$this->_saveValues($scope, $compiled)) {
                $this->Flash->error("Failed to update values");
            } else {
                $this->Flash->success("Saved!");
                //$this->redirect(['action' => 'manage', $scope]);
            }
        }

        $this->set('scope', $scope);

        $form = new SettingsForm();
        $form->setSettingsManager($this->settingsManager());
        $this->set('form', $form);

        $templateFile = sprintf(
            "%stemplates/%s/%s.php",
            Plugin::isLoaded($scope) ? Plugin::path($scope) : App::path('Template')[0],
            'Admin/Settings',
            'index'
        );
        if (file_exists($templateFile)) {
            //debug($templateFile);
            $this->viewBuilder()
                //->setPlugin($scope)
                ->setTemplatePath('Admin/Settings')
                ->setTemplate($scope . '.index');
        }
    }

    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
        $this->set('manager', $this->settingsManager());
        $this->set('result', $this->settingsManager()->describe());
    }

    /**
     * Form method
     *
     * @param string $scope Settings scope
     * @return void
    public function form($scope = SETTINGS_SCOPE)
    {
        $settingsForm = new SettingsForm($this->settingsManager());

        if ($this->request->is(['put', 'post'])) {
            // apply
            $settingsForm->execute($this->request->getData());

            // compile
            $compiled = $settingsForm->manager()->getCompiled();
            //Configure::write($compiled);

            // update
            if ($this->Settings->updateSettings($compiled, $scope)) {
                // dump
                $settingsForm->manager()->dump();

                $this->Flash->success('Settings updated');
                $this->redirect(['action' => 'index', $scope]);
            }
        }

        //$this->set('settings', $settings);
        $this->set('scope', $scope);
        $this->set('form', $settingsForm);
        $this->set('_serialize', ['settings']);
    }
     */

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $setting = $this->Settings->newEmptyEntity();
        if ($this->request->is('post')) {
            $setting = $this->Settings->patchEntity($setting, $this->request->getData());
            if ($this->Settings->save($setting)) {
                $this->Flash->success(__d('settings', 'The {0} has been saved.', __d('settings', 'setting')));

                return $this->redirect(['action' => 'edit', $setting->id]);
            } else {
                $this->Flash->error(__d('settings', 'The {0} could not be saved. Please, try again.', __d('settings', 'setting')));
            }
        }
        $this->set(compact('setting'));
        $this->set('valueTypes', $this->Settings->listValueTypes());
        $this->set('_serialize', ['setting']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Setting id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $setting = $this->Settings->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $setting = $this->Settings->patchEntity($setting, $this->request->getData());
            if ($this->Settings->save($setting)) {
                //$this->Settings->dump();
                $this->Flash->success(__d('settings', 'The {0} has been saved.', __d('settings', 'setting')));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__d('settings', 'The {0} could not be saved. Please, try again.', __d('settings', 'setting')));
            }
        }
        $this->set(compact('setting'));
        $this->set('_serialize', ['setting']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Setting id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $setting = $this->Settings->get($id);
        if ($this->Settings->delete($setting)) {
            $this->Flash->success(__d('settings', 'The {0} has been deleted.', __d('settings', 'setting')));
        } else {
            $this->Flash->error(__d('settings', 'The {0} could not be deleted. Please, try again.', __d('settings', 'setting')));
        }

        return $this->redirect(['action' => 'index']);
    }
}
