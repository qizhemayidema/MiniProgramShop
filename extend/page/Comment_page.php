<?php
namespace page;
	class Comment_page {
		public $page;   //当前页
		public $total; //总记录数
		public $listRows; //每页显示记录数
		private $uri;//动态url
		public $pageNum; //总页数
		private $listNum=4;//显示页码按钮数量
		public $render;//分页后的html模板
		public $data;//分页后渲染到模板的数据
		public $js_page_name;		//js翻页的函数名
		public $article_id;
		/*
		 * 初始化分页数据
		 * $sdata 待分页的数据长度
		 * $listRows 每页记录数
		 * $name js翻页的函数名
		 * $page 当前页
		 */
		public function __construct($sdata, $listRows=15,$name,$page,$article_id){
			$this->js_page_name = $name;
			$this->article_id = $article_id;
			$this->total = $sdata;
			$this->listRows=$listRows;
			$this->uri=$this->getUri();
			$this->page=$page;
			$this->pageNum=ceil($this->total/$this->listRows);
			$this->render=$this->pageHtml();
			// $this->data=array_slice($sdata,($this->page-1)*$this->listRows,$listRows);
			// return $this->data;
		}

		//动态获取url
		private function getUri(){
			$url=$_SERVER["REQUEST_URI"].(strpos($_SERVER["REQUEST_URI"], '?')?'':"?");
			$parse=parse_url($url);

			if(isset($parse["query"])){
				parse_str($parse['query'],$params);
				unset($params["page"]);
				$url=$parse['path'].'?'.http_build_query($params);
			}

			return $url;
		}

		//首页
		private function first(){
			$html = "";
			if($this->page==1)
				$html.="<span class='disabled'>首页</span>";
			else
				$html.="<a href='javascript:void(0)' onClick='".$this->js_page_name."(".$this->article_id.",1)'>首页</a>";
			return $html;
		}

		//上一页
		private function prev(){
			$html = "";
			if($this->page==1)
				$html.="<span class='disabled'>上一页</span>";
			else
				$html.="<a href='javascript:void(0)' onClick='".$this->js_page_name.'('.$this->article_id.','.($this->page-1).")'>上一页</a>";
			return $html;
		}

		//页码按钮
		private function pageList(){
			$linkPage="";

			$inum=floor($this->listNum/2);

			for($i=$this->page-$inum;$i<=$this->page+$inum;$i++){
				if($i<=0){
					continue;
				}
				if($i>$this->pageNum){
					continue;
				}
				if($i == $this->page){
					$linkPage.="<a class='current'>{$i}</a>";
				}else{
					$linkPage.=" <a href='javascript:void(0)' onClick='{$this->js_page_name}(".$this->article_id.",{$i})'>{$i}</a>";
				}
			}

			return $linkPage;
		}

		//下一页
		private function next(){
			$html = "";
			if($this->page==$this->pageNum || $this->total == 0)
				$html.="<span class='disabled'>下一页</span>";
			else
				$html.="<a href='javascript:void(0)' onClick='{$this->js_page_name}(".$this->article_id.','.($this->page+1).")'>下一页</a>";
			return $html;
		}

		//尾页
		private function last(){
			$html = "";
			if($this->page==$this->pageNum || $this->total == 0)
				$html.="<span class='disabled'>尾页</span>";
			else
				$html .="<a href='javascript:void(0)' onClick='{$this->js_page_name}(".$this->article_id.",{$this->pageNum})'>尾页</a>";
			return $html;
		}

		//输入指定页码
		private function goPage(){
			return '  <input class="input-text" type="text" onkeydown="javascript:if(event.keyCode==13){var page=(this.value>'.$this->pageNum.')?'.$this->pageNum.':this.value;location=\''.$this->uri.'&page=\'+page+\'\'}" value="'.$this->page.'" style="width:52px"><input class="btn btn-secondary" type="button" value="GO" onclick="javascript:var page=(this.previousSibling.value>'.$this->pageNum.')?'.$this->pageNum.':this.previousSibling.value;location=\''.$this->uri.'&page=\'+page+\'\'">  ';
		}

		//选择指定页码
		function selectPage(){
			$inum=10;
			$location = $this->uri.'&page=';
			$selectPage ="<span class='va-m'>到第 </span> <span class='select-box' style='width:initial'><select class='select' name='topage' size='1' onchange='window.location=\"$location\"+this.value'>";

			for($i=$this->page-$inum;$i<=$this->page+$inum;$i++){
				if($i<=0){
					continue;
				}
				if($i>$this->pageNum){
					continue;
				}
				if($i == $this->page){
					$selectPage .="<option value='$i' selected>$i</option>";
				}else{
					$selectPage .="<option value='$i'>$i</option>";
				}
			}
			$selectPage .="</select></span> <span class='va-m'>页</span>";
			return $selectPage;
		}

		//组装分页的html模板
		function pageHtml(){
			$html  = "<div class='cl mt-20 text-c'>";
			// $html .= "<span class='pr-20 va-m'>共有<b>{$this->total}</b>条记录</span>";
			// $html .= "<span class='pr-20 va-m'>每页显示<b>{$this->listRows}</b>条</span>";
			// $html .= "<span class='pr-20 va-m'><b>当前{$this->page}/{$this->pageNum}</b>页</span>";
			$html .= $this->first();
			$html .= $this->prev();
			$html .= $this->pageList();
			$html .= $this->next();
			$html .= $this->last();
			// $html .= $this->goPage();
			// $html .= $this->selectPage();
			$html .= '</div>';
			return $html;
		}
	}