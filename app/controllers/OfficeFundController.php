<?php
/**
*
*/
class OfficeFundController extends \BaseController
{

    public function listRepayments($id)
    {
        try {
            $list = Repayment::where('f_id', '=', $id)->get();
            $data = [];
            foreach ($list as $key => $repay) {
                $data[] = $repay->showInList();
            }
            $re = Tools::reTrue('获取提款计划成功', $data);
        } catch (Exception $e) {
            $re = Tools::reFalse($e->getCode(), '获取提款计划失败:'.$e->getMessage());
        }
        return Response::json($re);
    }

    public function interviewFund($id)
    {
        $check = Input::get('check', 0);
        $remark = Input::get('remark', '');

        try {
            $fund = Fund::find($id);
            if (empty($fund)) {
                throw new Exception("没有找到请求的基金记录", 10001);
            }
            if ($fund->t_status > 2) {
                throw new Exception("已经审核过了", 10001);
            }
            if ($check) {
                $fund->t_status = 3;
            } else {
                $fund->t_status = 1;
                $booth = Booth::find($fund->b_id);
                if (empty($booth)) {
                    throw new Exception("无与基金相关的店铺数据", 10001);
                }
                $booth->b_status = 2;
                $booth->save();
            }
            $fund->remark = $remark;
            $fund->interview();
            $re = Tools::reTrue('操作成功');
        } catch (Exception $e) {
            $re = Tools::reFalse($e->getCode(), '操作失败:'.$e->getMessage());
        }
        return Response::json($re);
    }
    
    public function allocateRepayment($id)
    {
        $comment = Input::get('comment', '');
        $img_token = Input::get('img_token', '');

        DB::beginTransaction();
        try {
            $repay = Repayment::find($id);
            if (empty($repay)) {
                throw new Exception("没有找到放款明细", 10001);
            }
            $repay->allocate($comment);
            if ($img_token) {
                $imgObj = new Img('loan', $img_token);
                $imgs = $imgObj->getSavedImg($repay->t_id);
                $repay->imgs = $imgs;
                $repay->save();
            }
            $fund = Fund::find($repay->f_id);
            if (empty($fund)) {
                throw new Exception("没有找到相关的基金", 1);
            }
            $fund->load('loans');
            if ($fund->chkAllRepaied()) {
                $fund->t_status = 5;
            }
            $fund->t_status = 4;
            $fund->save();

            $re = Tools::reTrue('放款成功');
            DB::commit();
        } catch (Exception $e) {
            $re = Tools::reFalse($e->getCode(), '放款失败:'.$e->getMessage());
            DB::rollback();
        }
        return Response::json($re);
    }

    public function listRepaiedFund()
    {
        $per_page = Input::get('per_page', 30);

        try {
            $query = Fund::with(['booth', 'loans'])->where('t_status', '>', 2);
            $list = $query->paginate($per_page);
            $data = [];
            foreach ($list as $key => $fund) {
                $tmp = $fund->showDetail();
                $tmp['last_income'] = $fund->getCurrentPeriodIncome();
                $data[] = $tmp;
            }
            $re = Tools::reTrue('获取基金收入列表成功', $data);
        } catch (Exception $e) {
            $re = Tools::reFalse($e->getCode(), '获取基金收入列表成功:'.$e->getMessage());
        }
        return Response::json($re);
    }

    public function retriveLoan($id)
    {
        DB::beginTransaction();
        $now = new DateTime();
        try {
            $current_loan = Repayment::find($id);
            if (empty($current_loan)) {
                throw new Exception("没有找到请求的放款", 6001);
            }
            $fund = Fund::find($current_loan->f_id);
            if (empty($fund)) {
                throw new Exception("没有找到相关的基金", 6001);
            }
            $current_income = $fund->getCurrentPeriodIncome();
            $current_loan->f_money = $current_income;
            $profit = $current_income - $current_loan->f_re_money;
            if ($profit > 0) {
                $wallet = UsersWalletBalances::find($fund->u_id);
                if (empty($wallet)) {
                    throw new Exception("没有获取到用户钱包", 6001);
                }
                $wallet->putIn($profit);
                $current_loan->f_status = 4;
            } elseif ($profit == 0) {
                $current_loan->f_status = 3;
            } elseif ($profit < 0) {
                $current_loan->f_status = 2;
            }
            $current_loan->repaied_at = $now->format('Y-m-d H:i:s');
            $current_loan->save();
            $fund->load('loans');
            $all_repaied = true;
            $total_loan = 0;
            $total_repay = 0;
            foreach ($fund->loans as $key => $loan) {
                if ($loan->f_status < 3) {
                    $all_repaied = false;
                }
                $total_loan += $loan->f_re_money;
                $total_repay += $loan->f_money;
            }
            if ($all_repaied) {
                $total_profit = $total_repay - $total_loan;
                if ($profit > 0) {
                    $qnck_profit = ($total_loan * (100 + $fund->t_profit_rate)) / 500;
                    $wallet = UsersWalletBalances::find($fund->u_id);
                    if (empty($wallet)) {
                        throw new Exception("没有获取到用户钱包", 6001);
                    }
                    $wallet->getOut($qnck_profit);
                }
                $fund->t_is_close = 1;
                $fund->closed_at = $now->format('Y-m-d H:i:s');
            } else {
                $fund->t_is_close = 0;
            }
            $fund->save();
            $re = Tools::reTrue('回收放款成功');
            DB::commit();
        } catch (Exception $e) {
            $re = Tools::reFalse($e->getCode(), '回收放款失败:'.$e->getMessage());
            DB::rollback();
        }
        return Response::json($re);
    }
}
