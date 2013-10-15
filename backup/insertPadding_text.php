// insertPadding test
$a =		array('2','3','5','7');
$b =		array('2','3','5');
$c =		array('2','3');
$d =		array('2');
$padding = 	array('(',':',')');

echo insertPadding($a, $padding). '<br/>';
echo insertPadding($b, $padding). '<br/>';
echo insertPadding($c, $padding). '<br/>';
echo insertPadding($d, $padding). '<br/>';

function insertPadding($data, $padding)
	{
		if (!is_array($data))
			return '<span class="error">insertPadding: $data is not array.</span>';
		foreach ($data as $i => &$piece)
			if (count($padding) === 2)
				$piece = $padding[0] . $piece . $padding[1];
			else
			{
				if ($i === 0)
					$piece = $padding[0] . $piece;
				if ($i === count($data)-1)
					$piece = $piece . $padding[2];
				else 
					$piece = $piece . $padding[1];
			}
		return implode($data);
	}