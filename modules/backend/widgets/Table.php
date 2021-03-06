<?php namespace Backend\Widgets;

use Input;
use Request;
use Backend\Classes\WidgetBase;
use System\Classes\SystemException;

/**
 * Table Widget.
 *
 * Represents an editable tabular control.
 * 
 * @package october\backend
 * @author Alexey Bobkov, Samuel Georges
 */
class Table extends WidgetBase
{
    /**
     * {@inheritDoc}
     */
    public $defaultAlias = 'table';

    /**
     * @var array Table columns
     */
    protected $columns = [];

    /**
     * @var boolean Show data table header
     */
    protected $showHeader = true;

    protected $dataSource = null;

    protected $recordsKeyFrom;

    protected $dataSourceAliases = [
        'client' => '\Backend\Classes\TableClientMemoryDataSource'
    ];

    /**
     * Initialize the widget, called by the constructor and free from its parameters.
     */
    public function init()
    {
        $this->columns = $this->getConfig('columns', []);

        $this->recordsKeyFrom = $this->getConfig('keyFrom', 'id');

        $dataSourceClass = $this->getConfig('dataSource');
        if (!strlen($dataSourceClass)) {
            throw new SystemException('The Table widget data source is not specified in the configuration.');
        }

        if (array_key_exists($dataSourceClass, $this->dataSourceAliases)) {
            $dataSourceClass = $this->dataSourceAliases[$dataSourceClass];
        }

        if (!class_exists($dataSourceClass)) {
            throw new SystemException(sprintf('The Table widget data source class "%s" is could not be found.', $dataSourceClass));
        }

        $this->dataSource = new $dataSourceClass($this->recordsKeyFrom);

        if (Request::method() == 'POST' && $this->isClientDataSource()) {
            $requestDataField = $this->alias.'TableData';

            if (Request::exists($requestDataField)) {
                // Load data into the client memory data source on POST
                $this->dataSource->purge();
                $this->dataSource->initRecords(Request::input($requestDataField));
            }
        }
    }

    /**
     * Returns the data source object.
     * @return \Backend\Classes\TableDataSourceBase 
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * Renders the widget.
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('table');
    }

    /**
     * Prepares the view data
     */
    public function prepareVars()
    {
        $this->vars['columns'] = $this->prepareColumnsArray();
        $this->vars['recordsKeyFrom'] = $this->recordsKeyFrom;

        $this->vars['recordsPerPage'] = $this->getConfig('recordsPerPage', false) ?: 'false';
        $this->vars['postbackHandlerName'] = $this->getConfig('postbackHandlerName', 'onSave');
        $this->vars['adding'] = $this->getConfig('adding', true);
        $this->vars['deleting'] = $this->getConfig('deleting', true);
        $this->vars['toolbar'] = $this->getConfig('toolbar', true);
        $this->vars['height'] = $this->getConfig('height', false) ?: 'false';

        $isClientDataSource = $this->isClientDataSource();

        $this->vars['clientDataSourceClass'] = $isClientDataSource ? 'client' : 'server';
        $this->vars['data'] = $isClientDataSource
            ? json_encode($this->dataSource->getAllRecords())
            : [];
    }

    //
    // Internals
    //

    /**
     * {@inheritDoc}
     */
    public function loadAssets()
    {
        $this->addCss('css/table.css', 'core');
        $this->addJs('js/table.js', 'core');
        $this->addJs('js/table.helper.navigation.js', 'core');
        $this->addJs('js/table.datasource.base.js', 'core');
        $this->addJs('js/table.datasource.client.js', 'core');
        $this->addJs('js/table.processor.base.js', 'core');
        $this->addJs('js/table.processor.string.js', 'core');
        $this->addJs('js/table.processor.checkbox.js', 'core');
        $this->addJs('js/table.processor.dropdown.js', 'core');
    }

    /**
     * Converts the columns associative array to a regular array.
     * Working with regular arrays is much faster in JavaScript.
     * References: 
     * - http://www.smashingmagazine.com/2012/11/05/writing-fast-memory-efficient-javascript/
     * - http://jsperf.com/performance-of-array-vs-object/3
     */
    protected function prepareColumnsArray()
    {
        $result = [];

        foreach ($this->columns as $key=>$data) {
            $data['key'] = $key;
            $result[] = $data;
        }

        return $result;
    }

    protected function isClientDataSource()
    {
        return $this->dataSource instanceof \Backend\Classes\TableClientMemoryDataSource;
    }

    /*
     * Event handlers
     */

    public function onGetDropdownOptions()
    {
        $columnName = Input::get('column');
        $rowData = Input::get('rowData');

        $eventResults = $this->fireEvent('table.getDropdownOptions', [$columnName, $rowData]);

        $options = [];
        if (count($eventResults)) {
            $options = $eventResults[0];
        }

        return [
            'options' => $options
        ];
    }
}
