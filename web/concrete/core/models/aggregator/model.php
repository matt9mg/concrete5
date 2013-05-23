<?
defined('C5_EXECUTE') or die("Access Denied.");
class Concrete5_Model_Aggregator extends Object {

	public function getAggregatorID() {return $this->agID;}
	public function getAggregatorDateCreated() {return $this->agDateCreated;}
	public function getAggregatorDateLastUpdated() {return $this->agDateLastUpdated;}
	public function getPermissionObjectIdentifier() { return $this->agID;}

	public static function getByID($agID) {
		$db = Loader::db();
		$r = $db->GetRow('select agID, agDateCreated, agDateLastUpdated from Aggregators where agID = ?', array($agID));
		if (is_array($r) && $r['agID'] == $agID) {
			$ag = new Aggregator;
			$ag->setPropertiesFromArray($r);
			return $ag;
		}
	}

	public static function getList() {
		$db = Loader::db();
		$r = $db->Execute('select agID from Aggregators order by agDateLastUpdated asc');
		$aggregators = array();
		while ($row = $r->FetchRow()) {
			$ag = Aggregator::getByID($row['agID']);
			if (is_object($ag)) {
				$aggregators[] = $ag;
			}
		}
		return $aggregators;
	}

	public static function add() {
		$db = Loader::db();
		$date = Loader::helper('date')->getSystemDateTime();
		$r = $db->Execute('insert into Aggregators (agDateCreated) values (?)', array($date));
		return Aggregator::getByID($db->Insert_ID());
	}


	public function getAggregatorItems() {
		$db = Loader::db();
		$r = $db->Execute('select agiID from AggregatorItems where agID = ?', array($this->agID));
		$list = array();
		while ($row = $r->FetchRow()) {
			$item = AggregatorItem::getByID($row['agiID']);
			if (is_object($item)) {
				$list[] = $item;
			}
		}
		return $list;
	}

	public function getConfiguredAggregatorDataSources() {
		$db = Loader::db();
		$r = $db->Execute('select acsID from AggregatorConfiguredDataSources where agID = ?', array($this->agID));
		$list = array();
		while ($row = $r->FetchRow()) {
			$source = AggregatorDataSourceConfiguration::getByID($row['acsID']);
			if (is_object($source)) {
				$list[] = $source;
			}
		}
		return $list;
	}

	public function clearConfiguredAggregatorDataSources() {
		$sources = $this->getConfiguredAggregatorDataSources();
		foreach($sources as $s) {
			$s->delete();
		}
	}

	public function duplicate() {
		$db = Loader::db();
		$newag = Aggregator::add();
		// dupe data sources
		foreach($this->getConfiguredAggregatorDataSources() as $source) {
			$source->duplicate($newag);
		}
		// dupe items
		foreach($this->getAggregatorItems() as $item) {
			$item->duplicate($newag);
		}
		return $newag;
	}

	/** 
	 * Runs through all active aggregator data sources, creates AggregatorItem objects
	 */
	public function generateAggregatorItems() {
		$configuredDataSources = $this->getConfiguredAggregatorDataSources();
		$items = array();
		foreach($configuredDataSources as $configuration) {
			$dataSource = $configuration->getAggregatorDataSourceObject();
			$dataSourceItems = $dataSource->createAggregatorItems($configuration);
			$items = array_merge($dataSourceItems, $items);
		}

		// now we loop all the items returned, and assign the batch to those items.
		$agiBatchTimestamp = time();
		$db = Loader::db();
		foreach($items as $it) {
			$it->setAggregatorItemBatchTimestamp($agiBatchTimestamp);
		}

		// now, we find all the items with that timestamp, and we update their display order.
		$agiBatchDisplayOrder = 0;
		$r = $db->Execute('select agiID from AggregatorItems where agID = ? and agiBatchTimestamp = ? order by agiPublicDateTime desc', array($this->getAggregatorID(), $agiBatchTimestamp));
		while ($row = $r->FetchRow()) {
			$db->Execute('update AggregatorItems set agiBatchDisplayOrder = ? where agiID = ?', array($agiBatchDisplayOrder, $row['agiID']));
			$agiBatchDisplayOrder++;
		}

		$date = Loader::helper('date')->getSystemDateTime();
		$db->Execute('update Aggregators set agDateLastUpdated = ? where agID = ?', array($date, $this->agID));

	}

	public function clearAggregatorItems() {
		$items = $this->getAggregatorItems();
		foreach($items as $it) {
			$it->delete();
		}
	}

	public function delete() {
		$db = Loader::db();
		$db->Execute('delete from Aggregators where agID = ?', array($this->getAggregatorID()));
		$this->clearConfiguredAggregatorDataSources();
		$this->clearAggregatorItems();
	}

}
