  <?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CronExchange extends Command
{
/**
 * The console command name.
 *
 * @var string
 */
protected $name = 'cron:exchange';

/**
 * The console command description.
 *
 * @var string
 */
protected $description = 'Importação de Cambio do BC';

/**
 * Timestamp atual
 *
 * @var integer
 */
protected $timestamp;

/**
 * Create a new command instance.
 *
 * @return void
 */
    public function __construct()
    {
        parent::__construct();
    }

/**
 * Execute the console command.
 *
 * @return mixed
 */

/**
 * Função fire realiza a chamada da função get_coins_exchange
 */
    public function fire()
    {
        
        Log::info("Starting exchange importation...");
        $this->checkQuote();
        $this->timestamp = time();
        
    }

		/**
 * função para realizar as cgamadas cURL, que é utilizada 
 * para transferir dados da API do  banco central pela URL
 * @param $url url externa da api Olinda Banco Central
 */
    protected function call_api($url)
    {
        $ch = curl_init($url); //iniciando 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // transforma as entradas do curl_exec
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//alguma coisa referente a verificação de certificados SSL da conexão
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $response = json_decode(curl_exec($ch));

        return $response;
    }

    protected function checkQuote(){
        $datePreviousMonth = date("Y-m-d",strtotime("-1 month")); //pega a data de hoje no mês passado
        $fechamento = $this->last_business_day($datePreviousMonth); //passa o datePreviousMonth para ser calculado o ultimo dia útil do mês passado
        $fechamento = $this->national_holliday($fechamento); //verifica se o dia de fechamento não é feriado nacional no Brasil
        $dateToFind = date('m-d-Y',  strtotime($fechamento)); //passa data convertida no formato m-d-Y para API do BC trazer a ultima cotação de fechamento
        $datePreviousDay = date("Y-m-d",strtotime("-1 day")); //pega data de ontem
        $dayPrevious = $this->last_working_day_week($datePreviousDay); //Verifica se a data de ontem é dia ultil
        $yesterday = date("m-d-Y",strtotime($dayPrevious)); //retorna a data do ultimo dia util $yesterday no formato aceito api BC
            
        $coins = $this->call_api('https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/Moedas?$top=100&$format=json');
                    
        $datecloseQuotation = $this->consultExistQuote($fechamento, true);

        if(!count($datecloseQuotation) > 0){
            $this->get_coins_exchange($dateToFind, $yesterday, $coins, $datecloseQuotation);
        }
        $dateYesterday = $this->consultExistQuote($dayPrevious, false);
        if(!count($dateYesterday) > 0){
            $this->get_coins_exchange($dateToFind, $yesterday, $coins, $dateYesterday);
        }
        
    }

/**
 * Função que consulta na base se existe um fechamento com as
 * datas do ultimo dia util ($dateYesterday) e com a ultima 
 * data de fechamento válida ($datecloseQuotation)
 * 
 */
    protected function consultExistQuote($date, $type=false){
        $closeQuotation = ExchangeRate::where('data_cotacao', '=', $date)
            ->where('fechamento', '=', $type ? 1 : 0)
            ->where('media', '=', $type ? 0 : 1)
            ->get();
        return $closeQuotation;
    }
/**
 * Função que executa a API Olinda do Banco Central,
 * trazendo como retorno os arquivos Json com dados
 * que serão inseridos pelo Cron na base de dados
 * $coins para as moedas, $exchange para o cambio
 * Passa os parametros $symbol_coins, $coins_quotation
 * para a função save_exchange
 */
    protected function get_coins_exchange($dateToFind, $yesterday, $coins, $closeQuotation)
    {			
        $coins_quotation = [];

        foreach ($coins->value as $coin) {
            
            $exchangeavg = $this->call_api('https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@moeda=\'' . $coin->simbolo . '\'&@dataInicial=\'' . $yesterday . '\'&@dataFinalCotacao=\'' . $yesterday . '\'&$top=100&$format=json&$select=paridadeCompra,paridadeVenda,cotacaoCompra,cotacaoVenda,dataHoraCotacao,tipoBoletim'); //url exchange do olinda banco central
            $exchange = $this->call_api('https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@moeda=\'' . $coin->simbolo . '\'&@dataInicial=\'' . $dateToFind. '\'&@dataFinalCotacao=\'' . $dateToFind . '\'&$top=100&$format=json&$select=paridadeCompra,paridadeVenda,cotacaoCompra,cotacaoVenda,dataHoraCotacao,tipoBoletim'); //url exchange do olinda banco central
            $value_exchangeavg = $this->iterationQuotation($exchangeavg);
            $value_exchange = $this->iterationQuotation($exchange);	

            if (!count($exchange->value && $exchangeavg->value) > 0) {
                continue;
            }

            if (!in_array($coin->simbolo, $coins_quotation)) {
                    $coins_quotation[$coin->simbolo] = $value_exchange;
                }
    
            }
        $key_coins = array_keys($coins_quotation);
        $symbol_coins = DB::table('currencies')->whereIn('symbol', $key_coins)->get();
        $this->save_exchange($symbol_coins, $coins_quotation, $dateToFind, $yesterday);
    }

    protected function iterationQuotation($exchangequote){
        $value_exchange = array();
        foreach ($exchangequote->value as $quote) {
                $value_exchange = array(
                    'paridadeCompra' => $quote->paridadeCompra,
                    'paridadeVenda' => $quote->paridadeVenda,
                    'cotacaoCompra' => $quote->cotacaoCompra,
                    'cotacaoVenda' => $quote->cotacaoVenda,
                    'tipoBoletim' => $quote->tipoBoletim
                );		
            }
        return $value_exchange;
    }
/**
 * Função para inserir os dados das moedas e suas cotações 
 * Função para inserir os dados das moedas e suas cotações 
 * Função para inserir os dados das moedas e suas cotações 
 * no Banco de dados na tabela exchange_rate
 * @param $symbol_coins retorna o objeto moeda com todos atributos
 * @param $coins_quotation retorna o array de cotação com 
 * @param $coins_quotation retorna o array de cotação com 
 * @param $coins_quotation retorna o array de cotação com 
 * paridadeCompra, paridadeVenda, cotacaoCompra, cotacaoVenda, tipoBoletim
 * da cotação 
 * da cotação 
 * da cotação 
 */
    protected function save_exchange($symbol_coins, $coins_quotation, $dateToFind, $yesterday)
    {
        $items = array();

        foreach ($symbol_coins as $item) {
            $symbol = trim($item->symbol);

            if (array_key_exists($symbol, $coins_quotation)) {
                $coins_quotation[$symbol]['currency_id'] = $item->id;
                $coins_quotation[$symbol]['tipo'] = $this->currencies_type($symbol);
            }
        }

        foreach ($coins_quotation as $item) {
            
            $insert = [
                'currency_id' => $item['currency_id'],
                'tipo' => $item['tipo'],
                'data_cotacao' => $dateToFind,
                'compra_real' => $item['cotacaoCompra'],
                'venda_real' => $item['cotacaoVenda'],
                'compra_paridade' => $item['paridadeCompra'],
                'venda_paridade' => $item['paridadeVenda'],
                'real_media' => ($this->rep($item['cotacaoCompra']) + $this->rep($item['cotacaoVenda'])) / 2,
                'paridade_media' => ($this->rep($item['cotacaoCompra']) + $this->rep($item['paridadeVenda'])) / 2,
                'fechamento' => 1
            ];

            $items[] = $insert;
        }	
        
        try {
            ExchangeRate::insert($items);
            $exclude_medias = substr($yesterday, 0, -2). '00';
            ExchangeRate::where('data_cotacao', '=', $exclude_medias)->delete(); //Exclui a média existente daquele mês
            $medias = ExchangeRate::where('data_cotacao','LIKE', substr($yesterday, 0, 4) . '-' . substr($yesterday, 5, 2) . "%")->get();

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
                    'data_cotacao' => substr($yesterday, 0, -2) . '00',
                    'compra_real' => 0,
                    'venda_real' => 0,
                    'compra_paridade' => 0,
                    'venda_paridade' => 0,
                    'media' => 1
                ];
                if ($yesterday === $dateToFind) {
                    $insert['fechamento'] = 1;
                }else {
                    $insert['fechamento'] = 0;
                }
            }

            ExchangeRate::insert($avg); // Insere a média mais atualizada
            
    
        } catch (Exception $e) {
            Log::info("Error in save_exchange");
            Log::info($e);

            return false;
        }
        
        Log::info("$dateToFind - Quotation successfully inserted");
        Log::info("$yesterday - Quotation successfully inserted");
        return;
    }

/**
 * Função para formatar string passando ',' para '.'
 * @param $str retorna a string formatada
 */
    protected function rep($str)
    {
        return str_replace(',', '.', $str);
    }

/**
 * Função que traz todos os feriados do Brasil 
 * Função que traz todos os feriados do Brasil 
 * Função que traz todos os feriados do Brasil 
 * cadastrados na tabela national_holliday
 * @param $date contém como informação a data do dia 
 * @param $date contém como informação a data do dia 
 * @param $date contém como informação a data do dia 
 */
    protected function national_holliday($date = '')
    {
        $holliday = '';
        $timestamp = strtotime($date);

        $holliday = DB::table('national_holidays')->where('data_feriado', '=', $date)->pluck('descricao');

        if ($holliday !== '' && $holliday !== null) {
            return $this->last_working_day_week(date('Y-m-d', strtotime('-1 day', $timestamp)));
        }

        return $this->last_working_day_week($date);
    }

/**
 * Função para checar o ultimo dia  util da semana
 * @param $data retorna o ultimo dia util da semana
 * @param $saida formata data em ano, mês e dia.
 */
    protected function last_working_day_week($data, $saida = 'Y-m-d')
    {
        // Converte $data em um UNIX TIMESTAMP
        $timestamp = strtotime($data);
        // Calcula qual o dia da semana de $data
        // O resultado será um valor numérico:
        // 1 -> Segunda ... 7 -> Domingo
        $dia = date('N', $timestamp);
        // Se for sábado (6) ou domingo (7), calcula a última-feira
        if ($dia == 6) {
            $timestamp_final = $timestamp - ((1) * 3600 * 24);
        } elseif ($dia == 7) {
            $timestamp_final = $timestamp - ((2) * 3600 * 24);
        } else {
            // Não é sábado nem domingo, mantém a data de entrada
            $timestamp_final = $timestamp;
        }
        return date($saida, $timestamp_final);
    }

/**
 * Função para checar o ultimo dia  util do mês
 * o ultimo dia util do mês é o dia do fechamento das
 * cotações do Banco Central
 * @param $data retorna o ultimo dia util do mês vigente
 */
    protected function last_business_day($datePreviousMonth = "")
    {
        $mes = substr($datePreviousMonth, 5, 2);
        $ano = substr($datePreviousMonth, 0, 4);
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
        
        return date("d-m-Y", $ultimo);
    }

/**
 * Função que carrega um array com os tipos de
 * moedas, esses tipos são usados nos calculos 
 * moedas, esses tipos são usados nos calculos 
 * moedas, esses tipos são usados nos calculos 
 * referente as moedas no balanço na controler
 * AccountingBalancesController.
 * coroa dinamarquesa (DKK) Tipo A
 *coroa norueguesa (NOK) Tipo A
*coroa sueca (SEK) Tipo A
*dólar americano (USD) Tipo A
*dólar australiano (AUD) Tipo B
*dólar canadense (CAD) Tipo A
*euro (EUR) Tipo B
*franco suíço (CHF) Tipo A
*iene (JPY) Tipo A
*libra esterlina (GBP) Tipo B 
* @param $symbol array com os simbolos das moedas
*/
    protected function currencies_type($symbol)
    {
        $array_types = array(
            "DKK" => "A",
            "NOK" => "A",
            "SEK" => "A",
            "USD" => "A",
            "AUD" => "B",
            "CAD" => "A",
            "EUR" => "A",
            "CHF" => "A",
            "JPY" => "A",
            "GBP" => "B"
        );

        if (array_key_exists($symbol, $array_types)) {
            return $array_types[$symbol];
        }
    }
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }
}
