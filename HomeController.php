<?php

namespace App\Controllers;

use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\LabTest;
use App\Helpers\Response;

/**
 * HomeController - Handles public website homepage and general features
 * Features: Homepage display, doctor search, featured content
 */
class HomeController
{
    private $doctorModel;
    private $medicineModel;
    private $labModel;

    public function __construct()
    {
        $this->doctorModel = new Doctor();
        $this->medicineModel = new Medicine();
        $this->labModel = new LabTest();
    }

    /**
     * Display homepage with featured doctors, medicines, and labs
     * GET /
     */
    public function index()
    {
        try {
            // Get featured doctors (verified, with good ratings)
            $featuredDoctors = $this->doctorModel->getFeaturedDoctors(limit: 6);

            // Get top specialties
            $specialties = $this->doctorModel->getAllSpecialties();

            // Get popular medicines
            $popularMedicines = $this->medicineModel->getPopularMedicines(limit: 8);

            // Get featured blog posts
            $blogPosts = $this->getBlogPosts(limit: 3);

            return view('pages/home', [
                'doctors' => $featuredDoctors,
                'specialties' => $specialties,
                'medicines' => $popularMedicines,
                'blogPosts' => $blogPosts,
                'title' => 'Digital Sehat Ghar - Online Doctor Consultation',
                'description' => 'Book online doctor consultation, lab tests, and pharmacy services in Pakistan'
            ]);
        } catch (\Exception $e) {
            return view('error', [
                'message' => 'Unable to load homepage',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Search doctors by specialty, city, name
     * GET /search/doctors
     * Parameters: specialty, city, search_query, page
     */
    public function searchDoctors()
    {
        try {
            $specialty = $_GET['specialty'] ?? null;
            $city = $_GET['city'] ?? null;
            $query = $_GET['query'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 12;

            $filters = [
                'specialty' => $specialty,
                'city' => $city,
                'search_query' => $query,
                'is_verified' => 1
            ];

            // Get total count for pagination
            $total = $this->doctorModel->countDoctors($filters);
            $totalPages = ceil($total / $perPage);

            // Validate page
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            // Get doctors
            $doctors = $this->doctorModel->searchDoctors($filters, limit: $perPage, offset: $offset);

            // Get filter options
            $specialties = $this->doctorModel->getAllSpecialties();
            $cities = $this->doctorModel->getAllCities();

            return view('pages/doctors/index', [
                'doctors' => $doctors,
                'specialties' => $specialties,
                'cities' => $cities,
                'currentSpecialty' => $specialty,
                'currentCity' => $city,
                'searchQuery' => $query,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Find Doctors | Digital Sehat Ghar'
            ]);
        } catch (\Exception $e) {
            logger()->error('Doctor search error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to search doctors',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * View doctor detail profile
     * GET /doctor/{id}
     */
    public function doctorDetail($id)
    {
        try {
            $doctor = $this->doctorModel->getDoctorById($id);

            if (!$doctor) {
                return view('error', [
                    'message' => 'Doctor not found',
                    'code' => 404
                ]);
            }

            // Get doctor reviews
            $reviews = $this->doctorModel->getDoctorReviews($id);

            // Get doctor availability
            $availability = $this->doctorModel->getAvailability($id);

            // Increment profile views
            $this->doctorModel->incrementProfileViews($id);

            return view('pages/doctors/detail', [
                'doctor' => $doctor,
                'reviews' => $reviews,
                'availability' => $availability,
                'title' => $doctor['full_name'] . ' - ' . $doctor['specialty']
            ]);
        } catch (\Exception $e) {
            logger()->error('Doctor detail error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load doctor profile',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Search medicines and pharmaceutical products
     * GET /search/medicines
     * Parameters: category, search_query, page
     */
    public function searchMedicines()
    {
        try {
            $category = $_GET['category'] ?? null;
            $query = $_GET['query'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 12;

            $filters = [
                'category' => $category,
                'search_query' => $query,
                'is_active' => 1
            ];

            // Get total count
            $total = $this->medicineModel->countMedicines($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            // Get medicines
            $medicines = $this->medicineModel->searchMedicines($filters, limit: $perPage, offset: $offset);

            // Get categories
            $categories = $this->medicineModel->getCategories();

            return view('pages/pharmacy/medicines', [
                'medicines' => $medicines,
                'categories' => $categories,
                'currentCategory' => $category,
                'searchQuery' => $query,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Buy Medicines Online | Digital Sehat Ghar'
            ]);
        } catch (\Exception $e) {
            logger()->error('Medicine search error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to search medicines',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * View medicine detail
     * GET /medicine/{id}
     */
    public function medicineDetail($id)
    {
        try {
            $medicine = $this->medicineModel->getMedicineById($id);

            if (!$medicine) {
                return view('error', [
                    'message' => 'Medicine not found',
                    'code' => 404
                ]);
            }

            // Get related medicines
            $related = $this->medicineModel->getRelatedMedicines($medicine['category'], exclude_id: $id);

            return view('pages/pharmacy/detail', [
                'medicine' => $medicine,
                'related' => $related,
                'title' => $medicine['name'] . ' - Buy Online'
            ]);
        } catch (\Exception $e) {
            logger()->error('Medicine detail error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load medicine details',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get lab tests listing with search and pagination
     * GET /search/labs
     * Parameters: search_query, category, page
     */
    public function searchLabTests()
    {
        try {
            $query = $_GET['query'] ?? null;
            $category = $_GET['category'] ?? null;
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 20; // 20 tests per page as per requirement

            $filters = [
                'search_query' => $query,
                'category' => $category,
                'is_active' => 1
            ];

            // Get total count
            $total = $this->labModel->countTests($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            // Get lab tests
            $tests = $this->labModel->searchTests($filters, limit: $perPage, offset: $offset);

            // Get categories
            $categories = $this->labModel->getCategories();

            return view('pages/labs/index', [
                'tests' => $tests,
                'categories' => $categories,
                'currentCategory' => $category,
                'searchQuery' => $query,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Lab Tests | Digital Sehat Ghar'
            ]);
        } catch (\Exception $e) {
            logger()->error('Lab search error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to search lab tests',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get about page
     * GET /about
     */
    public function about()
    {
        return view('pages/about', [
            'title' => 'About Digital Sehat Ghar'
        ]);
    }

    /**
     * Get contact page
     * GET /contact
     */
    public function contact()
    {
        return view('pages/contact', [
            'title' => 'Contact Us'
        ]);
    }

    /**
     * Get blog listing
     * GET /blog
     * Parameters: page, category
     */
    public function blog()
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $category = $_GET['category'] ?? null;
            $perPage = 10;

            $filters = [
                'status' => 'published',
                'category' => $category
            ];

            $total = $this->getBlogCount($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $posts = $this->getBlogPosts(limit: $perPage, offset: $offset, filters: $filters);
            $categories = $this->getBlogCategories();

            return view('pages/blog/index', [
                'posts' => $posts,
                'categories' => $categories,
                'currentCategory' => $category,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Health Blog | Digital Sehat Ghar'
            ]);
        } catch (\Exception $e) {
            logger()->error('Blog listing error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load blog posts',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get single blog post
     * GET /blog/{slug}
     */
    public function blogDetail($slug)
    {
        try {
            $post = $this->getBlogPost($slug);

            if (!$post) {
                return view('error', [
                    'message' => 'Blog post not found',
                    'code' => 404
                ]);
            }

            // Increment views
            $this->incrementBlogViews($post['id']);

            // Get related posts
            $related = $this->getRelatedBlogPosts($post['category'], exclude_id: $post['id']);

            return view('pages/blog/detail', [
                'post' => $post,
                'related' => $related,
                'title' => $post['title']
            ]);
        } catch (\Exception $e) {
            logger()->error('Blog detail error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load blog post',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get frequently asked questions
     * GET /faq
     */
    public function faq()
    {
        return view('pages/faq', [
            'title' => 'Frequently Asked Questions'
        ]);
    }

    /**
     * Handle contact form submission
     * POST /contact/submit
     */
    public function submitContact()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return Response::error('Invalid request method', 405);
            }

            $data = [
                'name' => sanitize($_POST['name'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'subject' => sanitize($_POST['subject'] ?? ''),
                'message' => sanitize($_POST['message'] ?? '')
            ];

            // Validation
            if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
                return Response::error('Please fill all required fields', 422);
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return Response::error('Invalid email address', 422);
            }

            // Send email (implementation needed)
            // TODO: Implement email sending

            // Log contact request
            logger()->info('Contact form submitted', [
                'email' => $data['email'],
                'subject' => $data['subject']
            ]);

            return Response::success('Thank you for contacting us. We will reply soon.', 200);
        } catch (\Exception $e) {
            logger()->error('Contact form error: ' . $e->getMessage());
            return Response::error('Error submitting contact form', 500);
        }
    }

    /**
     * Helper: Get blog posts (to be implemented with actual blog functionality)
     */
    private function getBlogPosts($limit = 10, $offset = 0, $filters = [])
    {
        // TODO: Implement blog retrieval from database
        return [];
    }

    /**
     * Helper: Get blog post count
     */
    private function getBlogCount($filters = [])
    {
        // TODO: Implement blog count
        return 0;
    }

    /**
     * Helper: Get single blog post by slug
     */
    private function getBlogPost($slug)
    {
        // TODO: Implement single blog retrieval
        return null;
    }

    /**
     * Helper: Get blog categories
     */
    private function getBlogCategories()
    {
        // TODO: Implement blog categories
        return [];
    }

    /**
     * Helper: Increment blog views
     */
    private function incrementBlogViews($postId)
    {
        // TODO: Implement view increment
    }

    /**
     * Helper: Get related blog posts
     */
    private function getRelatedBlogPosts($category, $exclude_id = null, $limit = 3)
    {
        // TODO: Implement related posts retrieval
        return [];
    }
}
