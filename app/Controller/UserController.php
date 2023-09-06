<?php

declare(strict_types=1);

namespace App\Controller;

use App\Models\User;
use HyperfExt\Jwt\Jwt;
use HyperfExt\Hashing\Hash;
use App\Constants\ErrorCode;
use Hyperf\Di\Annotation\Inject;
use App\Exception\BusinessException;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Controller;
use App\Middleware\Auth\AdminAuthMiddleware;
use HyperfExt\Jwt\Contracts\ManagerInterface;
use Hyperf\HttpServer\Annotation\Middlewares;
use App\Middleware\Auth\RefreshTokenMiddleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use HyperfExt\Jwt\Contracts\JwtFactoryInterface;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * @Controller(prefix="/user")
 * @package App\Controller
 */
class UserController extends AbstractController
{
    /**
     * 提供了对 JWT 编解码、刷新和失活的能力。
     * @var ManagerInterface
     */
    protected ManagerInterface $manager;

    /**
     * 提供了从请求解析 JWT 及对 JWT 进行一系列相关操作的能力。
     * @var Jwt
     */
    protected Jwt $jwt;

    /**
     * @Inject()
     * @var ValidatorFactoryInterface
     */
    protected ValidatorFactoryInterface $validationFactory;

    public function __construct(ManagerInterface $manager, JwtFactoryInterface $jwtFactory)
    {
        $this->manager = $manager;
        $this->jwt = $jwtFactory->make();
    }

    /**
     * register
     * @RequestMapping (path="register", methods={"POST"})
     */
    public function register(): PsrResponseInterface
    {
        return $this->success();
    }

    /**
     * @RequestMapping(path="login", methods={"POST"})
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return ResponseInterface|PsrResponseInterface
     */
    public function login(RequestInterface $request, ResponseInterface $response)
    {
        $input = $this->request->all();
        $validator = $this->validationFactory->make(
            $input,
            [
                'name'     => 'required',
                'password' => 'required',
            ],
            [
                'name.required'     => 'name is required',
                'password.required' => 'password is required',
            ]
        );
        if($validator->fails()){
            $errorMessage = $validator->errors()->first();
            throw new BusinessException(ErrorCode::FORBIDDEN, $errorMessage);
        }

        $credentials = $request->inputs(['name', 'password']);
        if(!$token = auth('api')->attempt($credentials)){
            return $response->withStatus(ErrorCode::UNAUTHORIZED)
                ->withContent('unauthorized');
        }
        return $this->respondWithToken($token);
    }

    /**
     * @RequestMapping(path="info")
     * @Middlewares({
     *     @Middleware(AdminAuthMiddleware::class),
     *     @Middleware(RefreshTokenMiddleware::class)
     * })
     * @param RequestInterface $request
     * @return PsrResponseInterface
     */
    public function info(RequestInterface $request): PsrResponseInterface
    {
        $uid = $request->getAttribute('user_id');
        $data = [
            'roles'        => ['admin'],
            'introduction' => 'I am a super administrator',
            'avatar'       => 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
            'name'         => 'Super Admin',
            'user_id'      => $uid,
        ];
        return $this->success($data);
    }

    public function refresh(): PsrResponseInterface
    {
        print_r(auth('api'));
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * @RequestMapping(path="logout", methods={"POST"})
     */
    public function logout(): PsrResponseInterface
    {
        auth('api')->logout();
        return $this->success(['message' => 'Successfully logged out']);
    }

    /**
     * @RequestMapping(path="page", methods={"GET"})
     */
    public function page($page = 1, $step = 10): PsrResponseInterface
    {
        $data = User::select()->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $step)
            ->limit($step)
            ->get();
        $total = User::count();

        return $this->paginator($total, $data);
    }

    /**
     * @RequestMapping(path="create", methods={"POST"})
     */
    public function create(RequestInterface $request): PsrResponseInterface
    {
        if($request->input('id')){
            $record = User::findOrFail($request->input('id'));
        }else{
            $record = new User();
        }
        if(!empty($password = $request->input('password'))){
            $record->password = Hash::make($password);
        }
        $record->name = $request->input('name');
        $record->phone = $request->input('phone');
        $record->email = $request->input('email');
        $record->sex = $request->input('sex');
        $record->age = $request->input('age');
        $record->status = $request->input('status');
        $record->save();
        return $this->success();
    }

    /**
     * @RequestMapping(path="delete", methods={"DELETE"})
     */
    public function delete(RequestInterface $request): PsrResponseInterface
    {
        $record = User::findOrFail($request->input('id'));
        $record->status = User::STATUS_DELETE;
        $record->save();
        return $this->success();
    }

    /**
     * @param $token
     * @return ResponseInterface
     */
    protected function respondWithToken($token): PsrResponseInterface
    {
        return $this->success(
            [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expire_in'    => make(JwtFactoryInterface::class)->make()->getPayloadFactory()->getTtl(),
            ]
        );
    }
}
