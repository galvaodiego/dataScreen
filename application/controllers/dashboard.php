<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {

	public function __construct()
	{
		parent::__construct();

		#ASANA
		$this->ASANA_CLIENT_ID = "407970087675707";
		$this->ASANA_CLIENT_SECRET = "1e2e5eec60b0977dd98c9a167e7dec92";
		$this->personal_acces_token = "0/68f96ada92ef065d57afdd6040649e70";

		#toggl
		// $this->toggl_token = "bea3ac2cfa40c66831c5c80f293c3080"; //galvao
		// $this->toggl_token = "dc3a39b81ab83020984882576eb8e90e"; // paulo
		$this->toggl_token = "a8b303cad6a7269d02232f7de1e5241c"; // renan

	}

	public function index()
	{
		#asana
		$asana = $this->getDadosAsana(); 
		$completed = 0;
		$pendent = 0;
		foreach ($asana['projeto'] as $projeto) {
			foreach ($projeto['tarefas'] as $tarefa) {
				if($tarefa->completed == 1){
					if($tarefa->assignee == ""){
						continue;
					}					
					$parte = explode("T", $tarefa->completed_at);
					$completo[$parte[0]][] = $tarefa->name;				
					$completed++;
				}else{
					$pendente[] = $tarefa->name; 
					$pendent++;
				}
			}
		}

		ksort($completo);
		foreach ($completo as $key => $value) {
			$parte = explode("-", $key);
			$grafico[$parte[1]][] = count($value);
		}
				
		foreach ($grafico as $key => $value) {
			$a = 0;
			foreach ($value as $k) {
				$a += $k;
			}

			$grafico2[$this->meses($key)] = $a;
		}

		// echo "<pre>"; print_r($grafico2); echo "</pre>";

		// echo "<pre>";
		// echo "TAREFAS COMPLETAS:".$completed;
		// echo "<br>";
		// print_r($completo);
		// echo "<br>";
		// echo "TAREFAS PENDENTES:".$pendent;
		// echo "<br>";
		// print_r($pendente);
		// echo "<br>";
		// echo "</pre>";

		#fim-asana


		#toggl

		$toggl = $this->getDadosToggl();
		// echo "<pre>"; print_r($toggl['dashboard']); echo "</pre>";
		$mural = array();
		$c = 0;
		foreach ($toggl['dashboard'] as $key) {
			// echo "<pre>"; print_r($key); echo "</pre>";
			$mural[$c] = $key['mau'];
			$c++;
		}

		echo "<pre>"; print_r($mural); echo "</pre>";



		#fim-toggl



		#dados para a view

		$data['dados_grafico_tarefas'] = $grafico2;
		$data['tarefas_pendentes'] = $pendent;
		$data['tarefas_completas'] = $completed;
		$data['tarefas_totais'] = ($completed+$pendent);

		#fim-dados para a view

		$this->load->view('dashboard',$data);		

	}


	private function getDadosToggl(){
		$toggl = new MorningTrain\TogglApi\TogglApi($this->toggl_token);
		
		$data = array();

		$workspaces = $toggl->getWorkspaces();
		foreach ($workspaces as $workspace) {
			$data[$workspace->id]['id'] = $workspace->id;
			$data[$workspace->id]['nome'] = $workspace->name;
			
			$users = $toggl->getWorkspaceUserRelations($workspace->id);
			foreach ($users as $user) {
				$data[$workspace->id]['usuarios'][$user->uid]['id'] = $user->id;
				$data[$workspace->id]['usuarios'][$user->uid]['uid'] = $user->uid;
				$data[$workspace->id]['usuarios'][$user->uid]['nome'] = $user->name;
				$data[$workspace->id]['usuarios'][$user->uid]['email'] = $user->email;
				$data[$workspace->id]['usuarios'][$user->uid]['ativo'] = $user->active;
				$data[$workspace->id]['usuarios'][$user->uid]['avatar'] = $user->avatar_file_name;
			}

			$projetos = $toggl->getWorkspaceProjects($workspace->id);
			foreach ($projetos as $projeto) {
				$data[$workspace->id]['projetos'][$projeto->id]['id'] = $projeto->id;
				$data[$workspace->id]['projetos'][$projeto->id]['wid'] = $projeto->wid;
				$data[$workspace->id]['projetos'][$projeto->id]['nome'] = $projeto->name;
				$data[$workspace->id]['projetos'][$projeto->id]['ativo'] = $projeto->active;
			}
		}

		foreach ($data as $wp) {
			$dwp = $toggl->getDashboadForWorkspace($wp['id']);
			$dashboard['wp_'.$wp['id']]['id'] = $wp['id']; 
			$dashboard['wp_'.$wp['id']]['nome'] = $wp['nome']; 
			$cont = 0;
			foreach ($dwp->most_active_user as $mau) {
				$dashboard['wp_'.$wp['id']]['mau'][$cont]['id_usuario'] = $mau->user_id;
				$dashboard['wp_'.$wp['id']]['mau'][$cont]['usuario'] = $data[$wp['id']]['usuarios'][$mau->user_id]['nome'];
				$dashboard['wp_'.$wp['id']]['mau'][$cont]['duracao'] = $mau->duration;
				$dashboard['wp_'.$wp['id']]['mau'][$cont]['avatar'] = $data[$wp['id']]['usuarios'][$mau->user_id]['avatar'];
				$cont++;
			}			

			$cont = 0;
			foreach ($dwp->activity as $act) {
				$dashboard['wp_'.$wp['id']]['act'][$cont]['id_usuario'] = $act->user_id;
				$dashboard['wp_'.$wp['id']]['act'][$cont]['usuario'] = $data[$wp['id']]['usuarios'][$act->user_id]['nome'];
				$dashboard['wp_'.$wp['id']]['act'][$cont]['duracao'] = $act->duration;
				$dashboard['wp_'.$wp['id']]['act'][$cont]['descricao'] = $act->description;
				$dashboard['wp_'.$wp['id']]['act'][$cont]['parou'] = $act->stop;
				$dashboard['wp_'.$wp['id']]['act'][$cont]['avatar'] = $data[$wp['id']]['usuarios'][$act->user_id]['avatar'];
				$cont++;
			}
		}

		$retorno['dados'] = $data;
		$retorno['dashboard'] = $dashboard;
		// echo "<pre>"; print_r($retorno); echo "</pre>"; exit();
		return $retorno;

	}

	private function getDadosAsana()
	{
		$client = Asana\Client::accessToken($this->personal_acces_token);
		$me = $client->users->me();

		$workspaceId = $me->workspaces[0]->id;

		$projetos = $client->projects->findAll(array('workspace'=>$workspaceId));
		$dados = array();
		$i = 0;
		$expand_task = array(
			'name',
			'assignee',
			'assignee_status',
			'created_at',
			'completed',
			'completed_at',
			'due_on',
			'due_at',
			'external',
			'modified_at',
			'notes'
		);
		foreach ($projetos as $projeto) {
		    $tarefas = $client->tasks->findByProject($projeto->id,array(),array('expand'=>$expand_task));
		    foreach ($tarefas as $tarefa) {
		    	$dados['projeto'][$i]['ID'] = $projeto->id;
		    	$dados['projeto'][$i]['nome'] = $projeto->name;
		    	$dados['projeto'][$i]['tarefas'][] = $tarefa;
		    }

		    $i++;

		}

		return $dados;		
	}


	private function meses($data){
		$mes = array(
			'01'=>'jan',
			'02'=>'fev',
			'03'=>'mar',
			'04'=>'abr',
			'05'=>'mai',
			'06'=>'jun',
			'07'=>'jul',
			'08'=>'ago',
			'09'=>'set',
			'10'=>'out',
			'11'=>'nov',
			'12'=>'dez'
			);

		return $mes[$data];
	}



}
