<?php $this->Breadcrumbs->add(__d('settings','Settings'), ['action' => 'index']); ?>
<?php $this->Breadcrumbs->add(__d('settings','New {0}', __d('settings','Setting'))); ?>
<?php $this->assign('title', __d('settings','Settings')); ?>
<?php $this->assign('heading', __d('settings','Add {0}', __d('settings','Setting'))); ?>
<div class="settings form">
    <?= $this->Form->create($setting, ['class' => 'setting']); ?>
    <?php
    echo $this->Form->control('scope');
    echo $this->Form->control('key');
    echo $this->Form->control('value');
    ?>
    <?= $this->Form->button(__d('settings','Submit')) ?>
    <?= $this->Form->end() ?>

</div>