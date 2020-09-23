<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

use Knp\Component\Pager\PaginatorInterface;

use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class VideoController extends AbstractController
{   
    private function resjson($data) {
        // Serializar datos con servicio serializer
        $json = $this->get('serializer')->serialize($data, 'json');
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El video no ha podido crearse'
        ];
        
        // Response con httpfoundation
        $response = new Response();

        // Asignar contenido a la respuesta
        $response->setContent($json);

        // Indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        // Devolver la respuesta
        return $response;
    }

    public function index()
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/VideoController.php',
        ]);
    }

    public function create(Request $request, JwtAuth $jwt_auth, $id = null) {
        
        // Recoger el token
        $token = $request->headers->get('Authorization', null);
       
        // Comprobar si es correcto
        $authCheck = $jwt_auth->checkToken($token);

        if ( $authCheck ) {
            // Recoger datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            // Recoger el objeto del usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            // Comprobar y validar datos

            if ( !empty($json) ) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = ($params->title != null ) ? $params->title : null;
                $description = ($params->description != null) ? $params->description : null;
                $url = ($params->url != null) ? $params->url : null;

                if ( !empty($user_id) && !empty($title) ) {
                    // Guardar el nuevo video favorito en la bd

                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getDoctrine()->getRepository(User::class)->findOneBy([
                        'id' => $user_id
                    ]);
                }

                if ( $id == null ) {
                    // Crear y guardar objeto
                    $video = new Video();
                    $video->setUser($user);
                    $video->setTitle($title);
                    $video->setDescription($description);
                    $video->setUrl($url);
                    $video->setStatus('normal');

                    $createdAt = new \Datetime('now');
                    $updatedAt = new \Datetime('now');
                    $video->setCreatedAt($createdAt);
                    $video->setUpdatedAt($updatedAt);

                    $em->persist($video);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'El video se ha creado correctamente'
                    ];
                } else {
                    $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                        'id' => $id,
                        'user' => $identity->sub
                    ]);

                    if ( $video && is_object($video) ) {
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus('normal');
    
                        $updatedAt = new \Datetime('now');
                        $video->setUpdatedAt($updatedAt);
    
                        $em->persist($video);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El video se ha actualizado correctamente',
                            'video' => $video
                        ];
                    }
                }
            }

        }

        return $this->resjson($data);
    }

    public function videos(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator) {

        // Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');

        // Comprobar el token
        $authCheck = $jwt_auth->checkToken($token);

        // Si es válido

        if ( $authCheck ) {
            // Conseguir la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            $em = $this->getDoctrine()->getManager();

            // Hacer una consulta para paginar
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC";
            $query = $em->createQuery($dql);

            // Recoger el parámetro page de la url
            $page = $request->query->getInt('page', 1);
            $items_per_page = 5;

            //Invocar paginatión
            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();

            // Preparar array de datos para devolver

            $data = array(
                'status' => 'success',
                'code' => 200,
                'total_items_count' => $total,
                'page_actual' => $page,
                'items_per_page' => $items_per_page,
                'total_pages' => ceil($total / $items_per_page),
                'videos' => $pagination,
                'user_id' => $identity->sub
            );
        } else {
            $data = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'No se pueden listar los videos en este momento'
            );
        }
        return $this->resjson($data);

    }

    public function video(Request $request, JwtAuth $jwt_auth, $id = null) {
        
        $token = $request->headers->get('Authorization', null);
        $authCheck = $jwt_auth->checkToken($token);
        
        $data = [
            'status' => 'error',
            'code' => 404,
            'message' => 'Video no encontrado',
            'ide' => $id
        ];

        if ( $authCheck ) {

            // Sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            // Sacar el objeto del video en base al id
            $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);

            // Comprobar si el video existe y es propiedad del usuario identificado
            if ( $video && is_object($video) && $identity->sub == $video->getUser()->getId() ) {
                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }        
        
        return $this->resjson($data);
    }

    public function remove(Request $request, JwtAuth $jwt_auth, $id = null) {

        $token = $request->headers->get('Authorization', null);
        $authCheck = $jwt_auth->checkToken($token);

        // Devolver una respuesta
        $data = [
            'status' => 'error',
            'code' => 404,
            'message' => 'Video no encontrado'
        ];

        if ( $authCheck ) {
            $identity = $jwt_auth->checkToken($token, true);

            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();
            $video = $doctrine->getRepository(Video::class)->findOneBy([
                'id' => $id
            ]);

            if ( $video && is_object($video) && $identity->sub == $video->getUser()->getId() ) {

                $em->remove($video);
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }
        
        return $this->resjson($data);
    }

}