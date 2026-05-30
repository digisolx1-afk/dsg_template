<?php

namespace App\Controllers;

use App\Models\Doctor;
use App\Models\Appointment;
use App\Models\Review;
use App\Helpers\Response;
use App\Helpers\Validator;

/**
 * DoctorController - Handles doctor-related public operations
 * Features: Doctor listings, profiles, reviews, ratings
 */
class DoctorController
{
    private $doctorModel;
    private $appointmentModel;
    private $reviewModel;

    public function __construct()
    {
        $this->doctorModel = new Doctor();
        $this->appointmentModel = new Appointment();
        $this->reviewModel = new Review();
    }

    /**
     * Get all doctors by specialty
     * GET /doctors/specialty/{specialty}
     */
    public function bySpecialty($specialty)
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $city = $_GET['city'] ?? null;
            $sortBy = $_GET['sort'] ?? 'rating'; // rating, experience, name
            $perPage = 12;

            // Validate specialty
            $specialtyData = $this->doctorModel->getSpecialtyBySlug($specialty);
            if (!$specialtyData) {
                return view('error', [
                    'message' => 'Specialty not found',
                    'code' => 404
                ]);
            }

            $filters = [
                'specialty' => $specialtyData['name'],
                'city' => $city,
                'is_verified' => 1
            ];

            // Get total count
            $total = $this->doctorModel->countDoctors($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            // Get doctors sorted
            $doctors = $this->doctorModel->getDoctorsBySpecialty(
                $specialty,
                city: $city,
                sortBy: $sortBy,
                limit: $perPage,
                offset: $offset
            );

            // Get cities for filter
            $cities = $this->doctorModel->getCitiesBySpecialty($specialty);

            return view('pages/doctors/specialty', [
                'specialty' => $specialtyData,
                'doctors' => $doctors,
                'cities' => $cities,
                'currentCity' => $city,
                'currentSort' => $sortBy,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => $specialtyData['name'] . ' in Pakistan'
            ]);
        } catch (\Exception $e) {
            logger()->error('Doctor specialty search error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load doctors',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get doctors by city
     * GET /doctors/city/{city}
     */
    public function byCity($city)
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $specialty = $_GET['specialty'] ?? null;
            $perPage = 12;

            // Validate city
            $cityData = $this->doctorModel->getCityBySlug($city);
            if (!$cityData) {
                return view('error', [
                    'message' => 'City not found',
                    'code' => 404
                ]);
            }

            $filters = [
                'city' => $cityData['name'],
                'specialty' => $specialty,
                'is_verified' => 1
            ];

            // Get total
            $total = $this->doctorModel->countDoctors($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $doctors = $this->doctorModel->getDoctorsByCity(
                $city,
                specialty: $specialty,
                limit: $perPage,
                offset: $offset
            );

            // Get specialties for filter
            $specialties = $this->doctorModel->getSpecialtiesByCity($city);

            return view('pages/doctors/city', [
                'city' => $cityData,
                'doctors' => $doctors,
                'specialties' => $specialties,
                'currentSpecialty' => $specialty,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Doctors in ' . $cityData['name']
            ]);
        } catch (\Exception $e) {
            logger()->error('Doctor city search error: ' . $e->getMessage());
            return view('error', [
                'message' => 'Unable to load doctors',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get doctor detail with full profile
     * GET /doctor/{id}
     */
    public function detail($id)
    {
        try {
            $doctor = $this->doctorModel->getDoctorById($id);

            if (!$doctor || !$doctor['is_verified']) {
                return view('error', [
                    'message' => 'Doctor not found',
                    'code' => 404
                ]);
            }

            // Get reviews
            $reviews = $this->reviewModel->getDoctorReviews($id, approved: true, limit: 10);
            $reviewStats = $this->reviewModel->getDoctorStats($id);

            // Get availability
            $availability = $this->doctorModel->getDoctorAvailability($id);

            // Increment profile views
            $this->doctorModel->incrementProfileViews($id);

            return view('pages/doctors/detail', [
                'doctor' => $doctor,
                'reviews' => $reviews,
                'reviewStats' => $reviewStats,
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
     * Get doctor reviews
     * GET /doctor/{id}/reviews
     */
    public function reviews($id)
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $sortBy = $_GET['sort'] ?? 'recent'; // recent, helpful, rating
            $perPage = 10;

            $doctor = $this->doctorModel->getDoctorById($id);
            if (!$doctor) {
                return Response::error('Doctor not found', 404);
            }

            $total = $this->reviewModel->countDoctorReviews($id, approved: true);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $reviews = $this->reviewModel->getDoctorReviews(
                $id,
                approved: true,
                sortBy: $sortBy,
                limit: $perPage,
                offset: $offset
            );

            $stats = $this->reviewModel->getDoctorStats($id);

            return Response::success('Reviews retrieved', 200, [
                'reviews' => $reviews,
                'stats' => $stats,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            logger()->error('Get doctor reviews error: ' . $e->getMessage());
            return Response::error('Unable to load reviews', 500);
        }
    }

    /**
     * Get doctor availability for appointment booking
     * GET /doctor/{id}/availability
     */
    public function getAvailability($id)
    {
        try {
            $locationType = $_GET['type'] ?? 'both'; // clinic, video, both
            $startDate = $_GET['start_date'] ?? date('Y-m-d');
            $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

            // Validate dates
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return Response::error('Invalid date format', 422);
            }

            if (strtotime($startDate) > strtotime($endDate)) {
                return Response::error('Start date must be before end date', 422);
            }

            $doctor = $this->doctorModel->getDoctorById($id);
            if (!$doctor) {
                return Response::error('Doctor not found', 404);
            }

            $availability = $this->appointmentModel->getDoctorTimeSlots(
                $id,
                $startDate,
                $endDate,
                $locationType
            );

            return Response::success('Availability retrieved', 200, [
                'availability' => $availability,
                'doctor' => [
                    'id' => $doctor['id'],
                    'name' => $doctor['full_name'],
                    'specialty' => $doctor['specialty'],
                    'consultationFee' => $doctor['consultation_fee'],
                    'videoFee' => $doctor['video_consultation_fee']
                ]
            ]);
        } catch (\Exception $e) {
            logger()->error('Get availability error: ' . $e->getMessage());
            return Response::error('Unable to get availability', 500);
        }
    }

    /**
     * Get top rated doctors
     * GET /doctors/top-rated
     */
    public function topRated()
    {
        try {
            $limit = (int)($_GET['limit'] ?? 10);
            $city = $_GET['city'] ?? null;

            $limit = min($limit, 50); // Max 50

            $doctors = $this->doctorModel->getTopRatedDoctors($limit, $city);

            return Response::success('Top rated doctors retrieved', 200, [
                'doctors' => $doctors
            ]);
        } catch (\Exception $e) {
            logger()->error('Get top rated doctors error: ' . $e->getMessage());
            return Response::error('Unable to load top rated doctors', 500);
        }
    }

    /**
     * Search doctors with advanced filters
     * GET /doctors/search
     */
    public function search()
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $perPage = 12;

            $filters = [
                'specialty' => $_GET['specialty'] ?? null,
                'city' => $_GET['city'] ?? null,
                'query' => $_GET['query'] ?? null,
                'minRating' => (float)($_GET['min_rating'] ?? 0),
                'availabilityType' => $_GET['availability'] ?? null,
                'is_verified' => 1
            ];

            $total = $this->doctorModel->countDoctors($filters);
            $totalPages = ceil($total / $perPage);

            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $perPage;

            $doctors = $this->doctorModel->searchDoctors(
                $filters,
                limit: $perPage,
                offset: $offset
            );

            $specialties = $this->doctorModel->getAllSpecialties();
            $cities = $this->doctorModel->getAllCities();

            return view('pages/doctors/search', [
                'doctors' => $doctors,
                'specialties' => $specialties,
                'cities' => $cities,
                'filters' => $filters,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
                'title' => 'Search Doctors'
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
     * Get doctor by ID for AJAX requests
     * GET /api/doctor/{id}
     */
    public function getDoctor($id)
    {
        try {
            $doctor = $this->doctorModel->getDoctorById($id);

            if (!$doctor) {
                return Response::error('Doctor not found', 404);
            }

            return Response::success('Doctor retrieved', 200, [
                'doctor' => $doctor
            ]);
        } catch (\Exception $e) {
            logger()->error('Get doctor error: ' . $e->getMessage());
            return Response::error('Unable to get doctor', 500);
        }
    }

    /**
     * Compare multiple doctors
     * GET /doctors/compare
     */
    public function compare()
    {
        try {
            $ids = $_GET['ids'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids)));

            if (count($ids) < 2 || count($ids) > 5) {
                return Response::error('Please select 2-5 doctors to compare', 422);
            }

            $doctors = $this->doctorModel->getDoctorsByIds($ids);

            return Response::success('Doctors retrieved for comparison', 200, [
                'doctors' => $doctors
            ]);
        } catch (\Exception $e) {
            logger()->error('Compare doctors error: ' . $e->getMessage());
            return Response::error('Unable to compare doctors', 500);
        }
    }

    /**
     * Helper: Validate date format
     */
    private function isValidDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
