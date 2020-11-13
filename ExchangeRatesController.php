<?php

class ExchangeRatesController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
	}

	/**
	 * Importa o arquivo das cotações
	 * 
	 * @return Response
	 */
	public function import()
	{
		$file = Input::file('select_file');
		
		if ($file->isValid())
		{
			$filename = $file->getClientOriginalName();
			$data['filename'] = $filename;

			$file->move(storage_path("csv/"), $filename);
			
			$filepath = storage_path("csv/") . $filename;

			if ($file->getClientMimeType() == 'text/csv' || $file->getClientMimeType() == 'application/octet-stream' || $file->getClientMimeType() == 'application/vnd.ms-excel')
			{
				$data['success'] = $this->import_file($filepath); 
		
				File::delete($filepath);
		
				return Response::json($data, 200);
			}
			else
			{
				File::delete($filepath);
				$data['success'] = false;
		
				return Response::json($data, 200);
			}
		}
		else
		{
			$data['success'] = false;
			return Response::json($data, 200);
		}
	}

	private function import_file($filepath)
	{
		$quotations = $this->csv_to_array($filepath);
		
		if ($quotations)
		{
			/**
			 * [0] => data (ddmmyyyy)
			 * [1] => código/id moeda
			 * [2] => tipo
			 * [3] => moeda
			 * [4] => compra_real
			 * [5] => venda_real
			 * [6] => compra_paridade
			 * [7] => venda_paridade
			 * 
			 */
			
			$items = array();
			$date = $quotations[0][0];
			
			$date = substr($date, 4) . '-' . substr($date, 2, 2) . '-' . substr($date, 0, 2);

			$fechamento = $this->last_business_day($date);
			
			$fechamento = $this->ultimoDiaUtil($fechamento);


			$importDate = ExchangeRate::where('data_cotacao', '=', $date)->delete();
			
			foreach ($quotations as $item)
			{	
				$insert = [
					'currency_id' => $item[1],
					'tipo' => $item[2],
					'data_cotacao' => $date,
					'compra_real' => $this->rep($item[4]),
					'venda_real' => $this->rep($item[5]),
					'compra_paridade' => $this->rep($item[6]),
					'venda_paridade' => $this->rep($item[7]),
					'real_media' => ($this->rep($item[4]) + $this->rep($item[5])) / 2,
					'paridade_media' => ($this->rep($item[6]) + $this->rep($item[7])) / 2 
				];

				if ($date == $fechamento)
				{
					$insert['fechamento'] = 1;
				}

				$items[] = $insert;
			}
			try {
				$result = ExchangeRate::insert($items);
				$exclude_medias = substr($fechamento, 0, -2). '00';

				ExchangeRate::where('data_cotacao', '=', $exclude_medias)->delete(); //Exclui a média existente daquele mês
				$medias = ExchangeRate::where('data_cotacao','LIKE', substr($fechamento, 0, 4) . '-' . substr($fechamento, 5, 2) . "%")->get();

				$avg_real  = array();
				$avg_dolar = array();
				$tipos = array();

				foreach ($medias as $media)
				{
					$avg_real[$media->currency_id][] = $media->real_media;
					$avg_dolar[$media->currency_id][] = $media->paridade_media;
					$tipos[$media->currency_id] = $media->tipo;
				}

				$avg = array();

				foreach ($avg_real as $key => $value)
				{
					$avg[$key]['real_media'] = (array_sum($value) / count($value));
				}

				foreach ($avg_dolar as $key => $value)
				{
					$avg[$key]['paridade_media'] = (array_sum($value) / count($value));
				}

				foreach ($avg as $key => $value)
				{
					$avg[$key] += [
						'currency_id' => $key,
						'tipo' => $tipos[$key],
						'data_cotacao' => substr($date, 0, -2) . '00',
						'compra_real' => 0,
						'venda_real' => 0,
						'compra_paridade' => 0,
						'venda_paridade' => 0,
						'fechamento' => 0,
						'media' => 1
					];
				}

				$result = ExchangeRate::insert($avg); // Insere a média mais atualizada
				
				
			} catch (Exception $e) {
				// TODO tratar exceção?
				return false;
			}
			return true;
			
		}
		
		return false;
	}

	/**
	* Função para calcular o último dia útil de uma data
	* Formato de entrada da $data: AAAA-MM-DD
	*/
	private function ultimoDiaUtil($data, $saida = 'Y-m-d') {
		// Converte $data em um UNIX TIMESTAMP
		$timestamp = strtotime($data);
		// Calcula qual o dia da semana de $data
		// O resultado será um valor numérico:
		// 1 -> Segunda ... 7 -> Domingo
		$dia = date('N', $timestamp);
		// Se for sábado (6) ou domingo (7), calcula a última-feira
		if ($dia == 6) {
			$timestamp_final = $timestamp - ((1) * 3600 * 24);
		}
		elseif ($dia == 7) {
			$timestamp_final = $timestamp - ((2) * 3600 * 24);
		} else {
			// Não é sábado nem domingo, mantém a data de entrada
			$timestamp_final = $timestamp;
		}
		return date($saida, $timestamp_final);
	}
	
	private function last_business_day($data="") 
    {
    	$mes = substr($data, 5, 2);
    	$ano = substr($data, 0, 4);

		$dias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
		$ultimo = mktime(0, 0, 0, $mes, $dias, $ano); 
		$dia = date("j", $ultimo);
		$dia_semana = date("w", $ultimo);

		if($dia_semana == 0){
		$dia--;
		$dia--;
		}
		if($dia_semana == 6)
		$dia--;
		$ultimo = mktime(0, 0, 0, $mes, $dia, $ano);
		
		return date("Y-m-d", $ultimo);
    }
	
	/**
	 * Troca a "vírgula" por "ponto" para inserir os valores no banco
	 * 
	 * @param string $str
	 * @return string
	 */
	private function rep($str)
	{
		return str_replace(',', '.', $str);
	}
	
	private function csv_to_array($filename, $delimiter=';')
	{
		if (!file_exists($filename) || !is_readable($filename))
		{
			return false;
		}

		$uploaded_file = array();
	
		if (($handle = fopen($filename, 'r')) !== FALSE)
		{
			$i = 0;
			ini_set('memory_limit', '-1');
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
			{
				$uploaded_file[] = $row;
			}
			fclose($handle);
	
			return $uploaded_file;
		}
	
		return false;
	}
	
	public function form()
	{
		$data['metaData'] = [
				'root' => 'fields'
		];
		
		$instructions = "{xtype:'displayfield',value:'".$this->data['contentTranslations']['exchangerates1']['text'].$this->data['contentTranslations']['exchangerates2']['text'].$this->data['contentTranslations']['exchangerates3']['text']."'},"
				. "{xtype:'fileuploadfield',id:'ff_exchangerateform_select_file',name:'select_file',fieldLabel:'" . $this->data['contentTranslations']['select_file']['text'] . "',labelWidth:150,allowBlank:false,anchor:'100%',buttonText:'" . $this->data['contentTranslations']['select_file']['text'] . "'}";		
		
		// TODO criar localização para o texto abaixo
		$data['fields'] = "["
				. $instructions
				. "]";
		
		return Response::json($data, 200);
	}
}
