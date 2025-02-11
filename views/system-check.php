<!-- realblog system check -->
<div class="realblog_systemcheck_container">
    <<?=$this->heading?>><?=$this->text('syscheck_title')?></<?=$this->heading?>>
<?php foreach ($this->checks as $label => $state):?>
        <p class="<?=$state?>">
            <?=$label?>
        </p>
<?php endforeach?>
</div>
