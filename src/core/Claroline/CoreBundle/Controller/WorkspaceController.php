<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Claroline\CoreBundle\Entity\Workspace\SimpleWorkspace;
use Claroline\CoreBundle\Form\WorkspaceType;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Claroline\CoreBundle\Library\Security\SymfonySecurity;

/**
 * This controller is able to:
 * - list/create/delete/show workspaces.
 * - return some users/groups list (ie: (un)registered users to a workspace).
 * - add/delete users/groups to a workspace.
 */
class WorkspaceController extends Controller
{
    const ABSTRACT_WS_CLASS = 'Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace';
    const NUMBER_USER_PER_ITERATION = 25;
    const NUMBER_GROUP_PER_ITERATION = 25;

    /**
     * Renders the workspace list page with its claroline layout.
     *
     * @throws AccessDeniedHttpException
     *
     * @return Response
     */
    public function listAction()
    {
        if (false === $this->get('security.context')->isGranted('ROLE_USER')) {
            throw new AccessDeniedHttpException();
        }

        $em = $this->get('doctrine.orm.entity_manager');
        $workspaces = $em->getRepository(self::ABSTRACT_WS_CLASS)->getNonPersonnalWS();

        return $this->render(
            'ClarolineCoreBundle:Workspace:list.html.twig',
            array('workspaces' => $workspaces)
        );
    }

    /**
     * Renders the registered workspace list for a user.
     *
     * @param integer $userId
     * @param string $format the format
     *
     * @throws AccessDeniedHttpException
     *
     * @return Response
     */
    public function listWorkspacesByUserAction($userId)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_USER')) {
            throw new AccessDeniedHttpException();
        }

        $em = $this->get('doctrine.orm.entity_manager');
        $user = $em->find('Claroline\CoreBundle\Entity\User', $userId);
        $workspaces = $em->getRepository(self::ABSTRACT_WS_CLASS)->getWorkspacesOfUser($user);

        return $this->render(
            "ClarolineCoreBundle:Workspace:list.html.twig",
            array('workspaces' => $workspaces)
        );
    }

    /**
     * Renders the workspace creation form
     *
     * @return Response
     */
    public function creationFormAction()
    {
        if (false === $this->get('security.context')->isGranted('ROLE_WS_CREATOR')) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->get('form.factory')->create(new WorkspaceType());

        return $this->render(
            'ClarolineCoreBundle:Workspace:form.html.twig',
            array('form' => $form->createView())
        );
    }

    /**
     * Create a workspace from a form sent by POST
     *
     * @return RedirectResponse
     *
     * @throws AccessDeniedHttpException
     */
    public function createAction()
    {
        if (false === $this->get('security.context')->isGranted('ROLE_WS_CREATOR')) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->get('form.factory')->create(new WorkspaceType());
        $form->bindRequest($this->getRequest());

        if ($form->isValid()) {
            $type = $form->get('type')->getData() == 'simple' ?
                Configuration::TYPE_SIMPLE :
                Configuration::TYPE_AGGREGATOR;
            $config = new Configuration();
            $config->setWorkspaceType($type);
            $config->setWorkspaceName($form->get('name')->getData());
            $config->setWorkspaceCode($form->get('code')->getData());
            $user = $this->get('security.context')->getToken()->getUser();
            $wsCreator = $this->get('claroline.workspace.creator');
            $wsCreator->createWorkspace($config, $user);
            $this->get('session')->getFlashBag()->add('success', 'Workspace created');
            $route = $this->get('router')->generate('claro_workspace_list');

            return new RedirectResponse($route);
        }

        return $this->render(
            'ClarolineCoreBundle:Workspace:form.html.twig',
            array('form' => $form->createView())
        );
    }

    /**
     * Delete a workspace and redirects to the desktop_index
     *
     * @param integer $workspaceId
     *
     * @return RedirectResponse
     *
     * @throws AccessDeniedHttpException
     */
    public function deleteAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);

        if (false === $this->get('security.context')->isGranted("ROLE_WS_MANAGER_{$workspaceId}", $workspace)) {
            throw new AccessDeniedHttpException();
        }

        $em->remove($workspace);
        $em->flush();

        return new Response('success', 204);
    }

    /**
     * Renders the home page with its layout.
     *
     * @param integer $workspaceId
     *
     * @return Response
     *
     * @throws AccessDeniedHttpException
     */
    public function homeAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $authorization = false;

        foreach ($workspace->getWorkspaceRoles() as $role) {
            $this->get('security.context')->isGranted($role->getName());
            {
                $authorization = true;
            }
        }

        if ($authorization === false) {
            throw new AccessDeniedHttpException();
        }

        return $this->render('ClarolineCoreBundle:Workspace:home.html.twig', array(
            'workspace' => $workspace,
            )
        );
    }

    /**
     * Renders the resources page with its layout.
     *
     * @param integer $workspaceId
     *
     * @return Response
     *
     * @throws AccessDeniedHttpException
     */
    public function resourcesAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $authorization = false;

        foreach ($workspace->getWorkspaceRoles() as $role) {
            $this->get('security.context')->isGranted($role->getName()); {
                $authorization = true;
            }
        }

        if ($authorization === false) {
            throw new AccessDeniedHttpException();
        }

        return $this->render('ClarolineCoreBundle:Workspace:resources.html.twig', array(
                'workspace' => $workspace,
                )
        );
    }

    /**
     * Renders the tools page with its layout.
     *
     * @param integer $workspaceId
     *
     * @return Response
     *
     * @throws AccessDeniedHttpException
     */
    public function toolsAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $authorization = false;

        foreach ($workspace->getWorkspaceRoles() as $role) {
            $this->get('security.context')->isGranted($role->getName()); {
                $authorization = true;
            }
        }

        if ($authorization === false) {
            throw new AccessDeniedHttpException();
        }

        return $this->render('ClarolineCoreBundle:Workspace:tools.html.twig', array(
                'workspace' => $workspace,
                )
        );
    }

    /**
     * Renders the user management page with its layout
     *
     * @param integer $workspaceId
     *
     * @return Response
     */
    public function userManagementAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $users = $em->getRepository('ClarolineCoreBundle:User')->getUsersOfWorkspace($workspace);

        return $this->render('ClarolineCoreBundle:Workspace:tools\user_management.html.twig', array(
                'workspace' => $workspace, 'users' => $users)
        );
    }

    /**
     * Renders the group management page with its layout
     *
     * @param integer $workspaceId
     *
     * @return Response
     */
    public function groupManagementAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $groups = $em->getRepository('ClarolineCoreBundle:Group')->getGroupsOfWorkspace($workspace);

        return $this->render('ClarolineCoreBundle:Workspace:tools\group_management.html.twig', array(
                'workspace' => $workspace, 'groups' => $groups)
        );
    }

    /**
     * Removes a user from a workspace.
     * if user id is null, the user will be the current logged user.
     * if it was requested through ajax, it will respond "success".
     * otherwise it'll redirect to the workspace list for a user.
     *
     * @param integer $userId
     *
     * @param integer $workspaceId
     *
     * @return Response|RedirectResponse
     */
    public function removeUserAction($userId, $workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $user = $em->find('Claroline\CoreBundle\Entity\User', $userId);
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $roles = $workspace->getWorkspaceRoles();

        foreach ($roles as $role) {
            $user->removeRole($role);
        }

        $em->flush();

        return new Response("success", 204);
    }

    /**
     * Removes a group from a workspace.
     * if it was requested through ajax, it'll respond "success"
     * otherwise it'll redirect to the user workspace list
     *
     * @param integer $groupId
     * @param integer $workspaceId
     *
     * @return Response|RedirectResponse
     */
    public function removeGroupAction($groupId, $workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $roles = $workspace->getWorkspaceRoles();

        foreach ($roles as $role) {
            $group->removeRole($role);
        }

        $em->flush();

        return new Response("success", 204);
    }

    /**
     * Renders a list of unregistered users for a workspace.
     * if page = 1, it'll render users 1-25
     * if page = 2, it'll render users 26-50
     * if page = 3, it'll render users 51-75
     * ...
     *
     * @param integer $workspaceId
     * @param integer $offset
     *
     * @return Response
     */
    public function paginatedUnregisteredUsersAction($workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $users = $em->getRepository('ClarolineCoreBundle:User')->getLazyUnregisteredUsersOfWorkspace($workspace, $offset, self::NUMBER_USER_PER_ITERATION);
        $content = $this->renderView("ClarolineCoreBundle:Administration:user_list.json.twig", array('users' => $users));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;

    }

    /**
     * Renders a list of unregistered groups for a workspace
     * if page = 1, it'll render groups 1-10
     * if page = 2, it'll render groups 11-20
     * if page = 3, it'll render groups 21-30
     * ...
     *
     * @param integer $workspaceId
     * @param integer $page
     *
     * @return Response
     */
    public function paginatedUnregisteredGroupsAction($workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $groups = $em->getRepository('ClarolineCoreBundle:Group')->getLazyUnregisteredGroupsOfWorkspace($workspace, $offset, self::NUMBER_GROUP_PER_ITERATION);
        $content = $this->renderView("ClarolineCoreBundle:Workspace:group.json.twig", array('groups' => $groups));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Renders a list of registered users for a workspace
     *
     * @param integer $workspaceId
     * @param integer $page
     *
     * @return Response
     */
    public function paginatedUsersOfWorkspaceAction($workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $users = $em->getRepository('ClarolineCoreBundle:User')->findPaginatedUsersOfWorkspace($workspaceId, $offset, self::NUMBER_USER_PER_ITERATION);
        $content = $this->renderView("ClarolineCoreBundle:Administration:user_list.json.twig", array('users' => $users));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Renders a list of registered groups for a workspace
     *
     * @param integer $workspaceId
     * @param integer $page
     *
     * @return Response
     */
    public function paginatedGroupsOfWorkspaceAction($workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $groups = $em->getRepository('ClarolineCoreBundle:Group')->findPaginatedGroupsOfWorkspace($workspaceId, $offset, self::NUMBER_GROUP_PER_ITERATION);
        $content = $this->renderView("ClarolineCoreBundle:Workspace:group.json.twig", array('groups' => $groups));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Renders a list of unregistered users for a workspace.
     * It'll search every users whose username or lastname or firstname match $search.
     *
     * @param string $search
     * @param integer $workspaceId
     * @param string $format
     *
     * @return Response
     */
    public function searchUnregisteredUsersAction($search, $workspaceId, $offset)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $users = $em->getRepository('ClarolineCoreBundle:User')->getUnregisteredUsersOfWorkspaceFromGenericSearch($search, $workspace, $offset, self::NUMBER_USER_PER_ITERATION);
        $content = $this->renderView("ClarolineCoreBundle:Administration:user_list.json.twig", array('users' => $users));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Adds a user to a workspace
     * if $userId = 'null', the user will be the current logged user
     * if requested through ajax, it'll respond with a json object containing the user datas
     * otherwise it'll redirect to the workspace list.
     *
     * @param integer $userId
     * @param integer $workspaceId
     *
     * @return RedirectResponse
     */
    public function addUserAction($userId, $workspaceId)
    {
        $request = $this->get('request');
        $em = $this->get('doctrine.orm.entity_manager');

        if ('null' != $userId) {
            $user = $em->find('Claroline\CoreBundle\Entity\User', $userId);
        } else {
            $user = $this->get('security.context')->getToken()->getUser();
        }

        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $user->addRole($workspace->getCollaboratorRole());
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->render('ClarolineCoreBundle:Administration:user_list.json.twig', array('users' => array($user)));
        }

        $route = $this->get('router')->generate('claro_workspace_list');

        return new RedirectResponse($route);
    }

    /**
     * Adds many users to a workspace.
     * It should be used with ajax and a list of userIds as parameter.
     *
     * @param integer $workspaceId
     *
     * @return Response
     */
    public function multiAddUserAction($workspaceId)
    {
        $params = $this->get('request')->query->all();
        unset($params['_']);

        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $users = array();

        foreach ($params as $userId) {
             $user = $em->find('Claroline\CoreBundle\Entity\User', $userId);
             $users[] = $user;
             $user->addRole($workspace->getCollaboratorRole());
             $em->flush();
        }

        //small hack to get the current workspace as the only workspace role. Do not flush after this !
        foreach ($users as $user){
            $user->setWorkspaceRoleCollection($workspace->getCollaboratorRole());
        }

        $content = $this->renderView('ClarolineCoreBundle:Administration:user_list.json.twig', array('users' => $users));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Adds a group to a workspace
     * if requested through ajax, it'll respond with a json object containing the group datas
     * otherwise it'll redirect to the workspace list.
     *
     * @param integer $groupId
     * @param integer $workspaceId
     *
     * @return RedirectResponse
     */
    public function addGroupAction($groupId, $workspaceId)
    {
        $request = $this->get('request');
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $group = $em->getRepository('ClarolineCoreBundle:Group')->find($groupId);
        $group->addRole($workspace->getCollaboratorRole());
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->render('ClarolineCoreBundle:Workspace:group.json.twig', array('groups' => array($group), 'workspace' => $workspace));
        }

        $route = $this->get('router')->generate('claro_workspace_list');

        return new RedirectResponse($route);
    }

    /**
     * Adds many groups to a workspace.
     * It should be used with ajax and a list of grouppIds as parameter.
     *
     * @param integer $workspaceId
     *
     * @return Response
     */
    public function multiAddGroupAction($workspaceId)
    {
        $params = $this->get('request')->query->all();
        unset($params['_']);

        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $groups = array();

        foreach ($params as $groupId) {
             $group = $em->find('Claroline\CoreBundle\Entity\Group', $groupId);
             $groups[] = $group;
             $group->addRole($workspace->getCollaboratorRole());
             $em->flush();
        }

        $content = $this->renderView('ClarolineCoreBundle:Workspace:group.json.twig', array('groups' => $groups));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Renders a list of unregistered groups for a workspace.
     * It'll search every groups whose name match $search.
     *
     * @param string $search
     * @param integer $workspaceId
     * @param string $format
     *
     * @return Response
     */
    public function searchUnregisteredGroupsAction($search, $workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $groups = $em->getRepository('ClarolineCoreBundle:Group')->getUnregisteredGroupsOfWorkspaceFromGenericSearch($search, $workspace);
        $content = $this->renderView("ClarolineCoreBundle:Workspace:group.json.twig", array('groups' => $groups));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Renders a list of registered users for a workspace.
     * It'll search every users whose username, firstname or lastname match $search.
     *
     * @param string $search
     * @param integer $workspaceId
     * @param string $format
     *
     * @return Response
     */
    public function searchRegisteredUsersAction($search, $workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $users = $em->getRepository('ClarolineCoreBundle:User')->searchPaginatedUsersOfWorkspace($workspaceId, $search, $offset, self::NUMBER_USER_PER_ITERATION);
        $content = $this->renderView("ClarolineCoreBundle:Administration:user_list.json.twig", array('users' => $users));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Returns the id of the current user workspace.
     *
     * @return Response
     */
    public function userWorkspaceIdAction()
    {
        $id = $this->get('security.context')->getToken()->getUser()->getPersonalWorkspace()->getId();

        return new Response($id);
    }

    /**
     *
     * @param type $workspaceId
     * @param type $format
     */
    public function rolesAction($workspaceId, $format)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $wsRoles = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId)->getWorkspaceRoles();

        return $this->render("ClarolineCoreBundle:Workspace:workspace_roles.{$format}.twig", array('roles' => $wsRoles));
    }

    /**
     * Renders the workspace properties page
     */
    public function propertiesAction($workspaceId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);

        return $this->render("ClarolineCoreBundle:Workspace:workspace_roles_properties.html.twig", array('workspace' => $workspace, 'masks' => SymfonySecurity::getResourcesMasks()));
    }
}