<?php 
namespace App\Libraries;
use Auth;
use DateTimeZone;
use DateTime;

class Criterias
{
	public static function getCriteriasList($modelName,$productType)
	{
		$returnCriterias = [];
		$criteria = config('criterias.'.$modelName);
		if(!empty($criteria))
		{
			$returnCriterias['default_criterias'] = $criteria['default_criterias'];
			$returnCriterias['optional_criterias'] = $criteria['optional_criterias_'.strtolower($productType)];
		}		
		return $returnCriterias;
	}
}